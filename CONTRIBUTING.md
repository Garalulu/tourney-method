# Contributing to Tourney Method

Thank you for helping improve Tourney Method. Bug fixes, tests, documentation,
translations, accessibility improvements, and focused feature proposals are
welcome.

## Development setup

Requirements:

- Docker Desktop with Docker Compose
- Git

```bash
git clone https://github.com/Garalulu/tourney-method.git
cd tourney-method
cp .env.example .env
docker compose up -d
docker compose exec -u sail laravel.test composer install
docker compose exec -u sail laravel.test npm ci
docker compose exec -u sail laravel.test php artisan key:generate
docker compose exec -u sail laravel.test php artisan migrate
```

The application is available at <http://localhost>.

## Before submitting a pull request

Tests are guarded against accidental production-database access. Always run
the isolation check before any test command:

```bash
docker compose exec -u sail laravel.test php artisan test:verify
docker compose exec -u sail laravel.test composer check
docker compose exec -u sail laravel.test composer test
docker compose exec -u sail laravel.test npm run build
```

External HTTP requests must be faked in tests. Use factories for database
fixtures and keep behavior changes covered by Pest tests.

## Pull requests

- Create a focused branch from `main`.
- Explain the problem and the chosen solution.
- Keep public routes, response shapes, migrations, and queue payloads backward
  compatible unless the pull request clearly documents a breaking change.
- Include tests and update relevant documentation.
- Do not include credentials, personal data, generated output, database dumps,
  or local environment files.

By contributing, you agree that your contribution is licensed under the MIT
License.
