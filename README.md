# Order Processing System

A robust, asynchronous event-driven order processing system built with Laravel. It features state machine management, provider simulation, idempotency, and retry mechanisms.

## How to Run Locally

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Set up your environment:**
   Copy `.env.example` to `.env` and configure your database settings. (By default, uses SQLite).
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run migrations:**
   ```bash
   php artisan migrate
   ```

4. **Start the local development server:**
   ```bash
   php artisan serve
   ```

The application will be accessible at `http://localhost:8000`.

## How the Async Worker Runs

The application uses Laravel's built-in queue system to process orders asynchronously without blocking the main API requests. 

To start the worker, run the following command in a separate terminal:
```bash
php artisan queue:work
```
This worker continuously listens for `ProcessOrderJob` and `CheckProviderStatusJob` tasks. It handles communication with the simulated external provider, manages state transitions (e.g., `RECEIVED` -> `SUBMITTED` -> `PENDING` -> `COMPLETED`/`FAILED`), and implements automatic retries when interacting with fluctuating endpoints.

## How to Trigger Provider Failures/Timeouts

The application includes a built-in provider simulation (`/api/provider/submit` and `/api/provider/status/{id}`) that probabilistically generates real-world API issues.

To see failures and timeouts in action, simply create a few orders via the API (ensure your queue worker is running!):
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"product_id": "prod_123", "quantity": 1, "customer_id": "cust_456"}'
```

Because the mock provider is built to be unreliable, by chance you will observe the following in your queue worker output and logs:
- **Timeouts/Delays:** 20% chance of a 2-3 second delay on submission.
- **500 Internal Errors:** 20% chance of throwing a 500 error on submission, and 10% chance on status checks. The async worker automatically catches these and attempts retries.
- **Final Failure:** 10% chance the final provider status resolves to `FAILED` instead of `COMPLETED`.

## Key Trade-offs

- **Database Queue vs Redis/SQS:** We are using the default database driver for queues. It is simple to set up and requires no extra infrastructure for a test project, but for high-throughput production environments, Redis or AWS SQS would be vastly more scalable.
- **Polling vs Webhooks:** The system currently dispatches delayed jobs to poll the provider for status updates. While this guarantees we check on the order regardless of provider capabilities, a webhook-based approach pushed from the provider would be much more resource-efficient and provide true real-time updates.
- **Cache for Idempotency:** We enforce idempotency checks (to avoid double-submitting to the provider) using Laravel's Cache. This is very fast and prevents duplicate work. However, if an ephemeral cache driver is used or the cache is cleared, duplicate requests could bypass the check. Alternatively, a unique constraint on an idempotency key database table would guarantee DB-level strict consistency at the cost of slight database overhead.
- **Co-located Mock Provider:** The simulated provider lives within the same Laravel application. This makes local testing completely self-contained and easy to spin up, but it means the mock provider shares the same server resources (CPU/memory) as the main application.
