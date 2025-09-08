# Deployment Architecture

## Deployment Strategy

**Frontend Deployment:**
- **Platform:** DigitalOcean App Platform (static files served via Nginx)
- **Build Command:** `cp -r public/* $OUTPUT_DIR/` (direct file copy)
- **Output Directory:** `public/` (web root)
- **CDN/Edge:** Built-in App Platform CDN with Singapore edge servers

**Backend Deployment:**
- **Platform:** DigitalOcean App Platform (managed PHP runtime)
- **Build Command:** `composer install --no-dev --optimize-autoloader`
- **Deployment Method:** Git-based automatic deployment from main branch

## CI/CD Pipeline
```yaml
# .do/app.yaml - DigitalOcean App Platform configuration
name: tourney-method
services:
- name: web
  source_dir: /
  github:
    repo: your-org/tourney-method
    branch: main
  run_command: |
    php scripts/setup/migrate.php
    php -S 0.0.0.0:8080 -t public/
  environment_slug: php
  instance_count: 1
  instance_size_slug: basic-xxs
  http_port: 8080
  routes:
  - path: /
  envs:
  - key: DATABASE_PATH
    value: /tmp/tournaments.db
  - key: TIMEZONE
    value: Asia/Seoul
  - key: OSU_CLIENT_ID
    value: ${OSU_CLIENT_ID}
  - key: OSU_CLIENT_SECRET
    value: ${OSU_CLIENT_SECRET}
    type: SECRET

jobs:
- name: daily-parser
  source_dir: /
  run_command: php scripts/parser/daily_parser.php
  schedule: "0 2 * * *"  # Daily at 2 AM KST
  
databases:
- name: tournaments-db
  engine: SQLITE
  production: true
```

## Environments

| Environment | Frontend URL | Backend URL | Purpose |
|-------------|-------------|-------------|---------|
| Development | http://localhost:8000 | http://localhost:8000/api | Local development |
| Staging | https://staging.tourneymethod.com | https://staging.tourneymethod.com/api | Pre-production testing |
| Production | https://tourneymethod.com | https://tourneymethod.com/api | Live environment |
