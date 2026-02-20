# Order Processing System

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
This worker continuously listens for `ProcessOrderJob`. It handles communication with the simulated external provider, manages state transitions (e.g., `RECEIVED` -> `SUBMITTED` -> `PENDING` -> `COMPLETED`/`FAILED`), and implements automatic retries when interacting with fluctuating endpoints.

## How to Trigger Provider Failures/Timeouts

The application includes a built-in provider simulation (`/api/provider/submit` and `/api/provider/status/{id}`) \

To see failures and timeouts in action, simply create a few orders via the API (ensure your queue worker is running!):
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"orderId": "order_123"}'
```

Because the mock provider is built to be unreliable, by chance you will observe the following in your queue worker output and logs:
- **Timeouts/Delays:** 20% chance of a 2-3 second delay on submission.
- **500 Internal Errors:** 20% chance of throwing a 500 error on submission, and 10% chance on status checks. The async worker automatically catches these and attempts retries.
- **Final Failure:** 10% chance the final provider status resolves to `FAILED` instead of `COMPLETED`.

- 
## Health Check Endpoint

The application includes a system health check endpoint located at `/health` returning a `200 OK` JSON response.

```bash
curl http://localhost:8000/health
```

**Expected Response:**
```json
{
  "status": "ok"
}
```

If the database connection fundamentally fails, it returns a `500 Internal Server Error` with `{"status": "error", "message": "Database connection failed"}`.

## Key Trade-offs
- - **Polling vs Webhooks:** Used polling for guaranteed updates; Webhooks would be more efficient.
- **Cache for Idempotency:** Used Cache for speed; Database unique constraints would be safer.
- Code would be refactored with services and action classes to make it more testable and maintainable.

