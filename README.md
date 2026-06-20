# Tourney Method

Tourney Method is an open-source tournament discovery and history platform for
the osu! community. It collects tournament information, helps players discover
eligible events, records staff and podium history, and provides moderated
community contribution workflows.

> Project status: early public release. Interfaces may evolve before version
> 1.0, but security-sensitive and destructive changes receive explicit review.

## Features

- Tournament discovery with mode, rank, badge, region, and registration filters
- Tournament forum parsing and review history
- Player profiles, badges, rank history, participation records, and year recaps
- Match import and lobby lookup
- Tournament staff and podium management
- Community-submitted tournament additions and corrections
- In-app and Discord notifications
- English, Korean, Russian, Spanish, Simplified Chinese, and Traditional Chinese
- Queue monitoring, backups, maintenance commands, and health checks

## Screenshots

Screenshots are being prepared for the first public release. The local
application is available at <http://localhost> after completing the setup below.

## Technology

- PHP 8.4+ and Laravel 12
- PostgreSQL and Redis
- Livewire, Alpine.js, Tailwind CSS, and Vite
- Pest, PHPStan, and Laravel Pint
- Docker Compose with Laravel Sail-compatible containers

## Architecture

The application uses conventional Laravel HTTP, model, service, job, and
console layers. Larger business areas are being incrementally organized under:

```text
app/
├── Domain/
│   ├── Participation/
│   └── Tournaments/
│       ├── Corrections/
│       ├── Parsing/
│       ├── Podium/
│       └── Staff/
├── Integrations/
│   ├── Discord/
│   ├── Osu/
│   ├── Otr/
│   ├── Sip/
│   ├── Tcomm/
│   └── Twitch/
└── Support/
```

See [docs/CODEMAPS/architecture.md](docs/CODEMAPS/architecture.md) for the
request, queue, storage, and integration flows.

## Local development

### Requirements

- Docker Desktop with Docker Compose
- Git

### Installation

```bash
git clone https://github.com/Garalulu/tourney-method.git
cd tourney-method
cp .env.example .env
docker compose up -d
docker compose exec -u sail laravel.test composer install
docker compose exec -u sail laravel.test npm ci
docker compose exec -u sail laravel.test php artisan key:generate
docker compose exec -u sail laravel.test php artisan migrate
docker compose exec -u sail laravel.test npm run build
```

Optional integrations are disabled until their corresponding environment
variables are configured. Never commit a populated environment file.

Access points:

- Application: <http://localhost>
- Horizon: <http://localhost/horizon>
- Mailpit: <http://localhost:8025>

## Testing and quality

Tests must never connect to the production database. Run the isolation
verification before every Pest command:

```bash
docker compose exec -u sail laravel.test php artisan test:verify
docker compose exec -u sail laravel.test composer test
docker compose exec -u sail laravel.test composer check
docker compose exec -u sail laravel.test npm run build
```

Individual suites are available through `composer test-unit`,
`composer test-feature`, `composer test-integration`, and
`composer test-livewire`.

## Configuration and deployment

- [Environment variables](docs/ENV.md)
- [Commands](docs/COMMANDS.md)
- [Queues](docs/QUEUES.md)
- [Testing](docs/TESTING.md)
- [Operations runbook](docs/RUNBOOK.md)
- [Railway deployment](docs/DEPLOYMENT_RAILWAY.md)
- [Application API](docs/openapi.yaml)

Production operators are responsible for database backups, credential
rotation, HTTPS, queue monitoring, and access control.

## Contributing

Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request. Please
use the issue forms for bugs, features, and documentation changes.

Security vulnerabilities must be reported privately according to
[SECURITY.md](SECURITY.md).

## License

Tourney Method is available under the [MIT License](LICENSE).
