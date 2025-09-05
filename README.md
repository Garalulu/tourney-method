# Tourney Method

Korean osu! tournament discovery platform - helping Korean players find and join tournaments more easily.

## Overview

A vanilla PHP + jQuery + SQLite application optimized for Korean market deployment on DigitalOcean App Platform. Features automated tournament parsing from osu! forums and admin approval workflow.

## Tech Stack

- **Backend**: Vanilla PHP 8.1+ (no frameworks)
- **Frontend**: jQuery + Pico.css
- **Database**: SQLite (with PostgreSQL evolution path)
- **Deployment**: DigitalOcean App Platform (Singapore region)

## Quick Start

### Local Development

1. **Clone and setup**:
   ```bash
   git clone <repository-url>
   cd tourney-method
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your osu! OAuth credentials
   ```

4. **Start development server**:
   ```bash
   php -S localhost:8000 -t public/
   ```

5. **Visit**: http://localhost:8000

### Database

The SQLite database is pre-configured with schema in `data/database/`. No additional setup required for development.

## Project Structure

```
├── public/          # Web root (index.php, admin/, api/, assets/)
├── src/             # PHP application code (models/, services/, repositories/)
├── data/            # Database and migrations
├── config/          # Environment configuration
├── scripts/         # CLI utilities and cron jobs
├── tests/           # Test suites
└── docs/            # Documentation and specifications
```

## Deployment

Optimized for DigitalOcean App Platform with automatic SSL, CDN, and Korean market optimization.

See `docs/DEPLOYMENT.md` for full deployment instructions.

## Development

- **Testing**: `composer test`
- **Standards**: PSR-4 autoloading, PSR-12 coding standards
- **Security**: Prepared statements, CSRF protection, input validation

## License

MIT