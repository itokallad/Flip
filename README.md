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

## API Client Usage (Postman / ThunderClient)

If you are using an API client like Postman, you must pass the `Accept: application/json` header. 

Laravel defaults to returning a `302 Found` redirect back to the home page if a FormRequest validation fails on what it thinks is a web browser. By setting `Accept: application/json`, Laravel knows you are an API client and will correctly return `422 Unprocessable Content` JSON validation errors instead.

Make sure your body is also set to `raw` -> `JSON`.

## How the Async Worker Runs

The application uses Laravel's built-in queue system to process orders asynchronously without blocking the main API requests. 

To start the worker, run the following command in a separate terminal:
```bash
php artisan queue:work
```
This worker continuously listens for `ProcessOrderJob`. It handles communication with the simulated external provider, manages state transitions (e.g., `RECEIVED` -> `SUBMITTED` -> `PENDING` -> `COMPLETED`/`FAILED`), and implements automatic retries when interacting with fluctuating endpoints.

## Testing Provider Failures and Retries

The application includes a built-in provider simulation (`/api/provider/submit` and `/api/provider/status/{id}`) built for deterministic testing of different real-world API states.

Because the mock provider is built to be deterministic, you must use specific `orderId` prefixes to trigger failures.

### Deterministic Failure Testing
To guarantee the mock provider ultimately returns a `FAILED` status, prefix your `orderId` payload with `fail_`.
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"orderId": "fail_12345"}'
```

### Deterministic Retry Testing (500 Errors)
To test the async worker's ability to gracefully catch and retry 500 Internal Server errors from the provider, prefix your `orderId` payload with `retry_`.

The mock provider will intentionally throw a `500` error on the first two status checks, forcing the `ProcessOrderJob` to fail and retry. On the third attempt, it will successfully return `COMPLETED`.
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"orderId": "retry_12345"}'
```

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
- **Polling vs Webhooks:** Used polling for guaranteed updates; Webhooks would be more efficient.
- **Cache for Idempotency:** Used Cache for speed; Database unique constraints would be safer.
- Code would be refactored with services and action classes to make it more testable and maintainable.

