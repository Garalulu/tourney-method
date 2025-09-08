# Development Workflow

## Local Development Setup

### Prerequisites
```bash
# Required software for development
php --version    # PHP 8.1 or higher
sqlite3 --version # SQLite 3.40 or higher
git --version    # Git for version control

# Optional but recommended
composer --version # For development dependencies
```

### Initial Setup
```bash
# Clone repository
git clone https://github.com/your-org/tourney-method.git
cd tourney-method

# Install development dependencies
composer install

# Create environment configuration
cp config/development.php.example config/development.php

# Initialize SQLite database
php scripts/setup/install.php

# Run database migrations
php scripts/setup/migrate.php

# Set file permissions (Unix/Linux)
chmod 600 data/database/tournaments.db
chmod -R 644 public/
chmod -R 600 src/ config/ scripts/
```

### Development Commands
```bash
# Start all services
php -S localhost:8000 -t public/

# Start frontend only (static assets)
python -m http.server 8080 --directory public/assets

# Start backend only (API testing)
php -S localhost:3000 -t public/api/

# Run tests
./vendor/bin/phpunit tests/
./vendor/bin/phpunit tests/unit/
./vendor/bin/phpunit tests/integration/
```

## Environment Configuration

### Required Environment Variables
```bash
# Frontend (.env.local)
VITE_API_BASE_URL=http://localhost:8000/api
VITE_OSU_CLIENT_ID=your_osu_client_id

# Backend (.env)
DATABASE_PATH=data/database/tournaments.db
OSU_CLIENT_ID=your_osu_client_id
OSU_CLIENT_SECRET=your_osu_client_secret
OSU_REDIRECT_URI=http://localhost:8000/admin/oauth/callback
SESSION_SECRET=your_random_session_secret

# Shared
TIMEZONE=Asia/Seoul
ENVIRONMENT=development
DEBUG_MODE=true
LOG_LEVEL=debug
```
