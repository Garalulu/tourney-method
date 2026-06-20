# Development

Use Docker Compose for local development. The commands below run as the
container's `sail` user so generated files remain writable.

## Initial setup

```bash
cp .env.example .env
docker compose up -d
docker compose exec -u sail laravel.test composer install
docker compose exec -u sail laravel.test npm ci
docker compose exec -u sail laravel.test php artisan key:generate
docker compose exec -u sail laravel.test php artisan migrate
docker compose exec -u sail laravel.test npm run build
```

The application is available at <http://localhost>. Mailpit is available at
<http://localhost:8025>.

## Common commands

```bash
# Application
docker compose exec -u sail laravel.test php artisan about
docker compose exec -u sail laravel.test php artisan route:list
docker compose exec -u sail laravel.test php artisan list

# Frontend
docker compose exec -u sail laravel.test npm run dev
docker compose exec -u sail laravel.test npm run build

# Code quality
docker compose exec -u sail laravel.test composer pint
docker compose exec -u sail laravel.test composer phpstan
docker compose exec -u sail laravel.test composer check
```

Run `php artisan help <command>` for command-specific options. Optional external
integrations remain disabled until their placeholder environment values are
replaced locally. Never commit a populated environment file.

## Contribution boundaries

Preserve public routes, response shapes, database schema, queue payloads, model
relationships, and existing service entrypoints unless a change is explicitly
proposed as breaking. Keep controllers focused on HTTP concerns and place
reusable business behavior in domain or service classes.
