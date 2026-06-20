# Testing

Tests must never connect to the production database. The primary safety check
runs in `Tests\TestCase::setUp()` before traits such as `RefreshDatabase` can
execute migrations.

## Mandatory verification

Run this command before every Pest invocation:

```bash
docker compose exec -u sail laravel.test php artisan test:verify
```

Do not continue if it reports an error.

## Test suites

```bash
docker compose exec -u sail laravel.test composer test-unit
docker compose exec -u sail laravel.test composer test-feature
docker compose exec -u sail laravel.test composer test-integration
docker compose exec -u sail laravel.test composer test-livewire
```

The complete contributor check is:

```bash
docker compose exec -u sail laravel.test php artisan test:verify
docker compose exec -u sail laravel.test composer check
docker compose exec -u sail laravel.test composer test
docker compose exec -u sail laravel.test npm run build
```

## Test conventions

- Use factories for persisted fixtures.
- Fake all external HTTP requests.
- Prefer `RefreshDatabase` for database tests.
- Use Laravel time travel instead of `sleep()`.
- Add focused regression coverage for bug fixes.
- Do not truncate or manually clear shared tables.
