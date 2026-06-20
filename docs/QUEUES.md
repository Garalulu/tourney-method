# Queues

Background work uses Laravel jobs and Redis. Horizon provides local visibility
into queued, running, completed, and failed jobs.

## Queue names

Shared osu! queue names are defined in `App\Support\QueueNames`:

- `osu-admin-priority`
- `osu-admin`
- `osu-user`

Other workflows may use focused names such as `imports` or the default queue.
Do not repeat a queue-name string across new call sites; add a shared constant
when the name is reused.

## Job conventions

- Keep serialized payloads small and stable.
- Pass model identifiers when a model can be reloaded safely.
- Make retryable jobs idempotent.
- Configure timeouts, retry counts, and backoff for provider calls.
- Use batches or chains when ordering and aggregate progress matter.
- Put provider-specific translation behind an integration or service boundary.
- Add tests for dispatch, queue selection, failure handling, and duplicate runs.

## Local development

```bash
docker compose exec -u sail laravel.test php artisan horizon
docker compose exec -u sail laravel.test php artisan horizon:status
docker compose exec -u sail laravel.test php artisan queue:failed
```

Tests normally use the synchronous queue connection. Use queue fakes when
asserting dispatch behavior and execute a job directly when testing its domain
effects.
