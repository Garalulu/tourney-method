#!/bin/bash
# Railway Scheduler Service - long-running Laravel scheduler.
# Use this for an always-on Railway service. Use run-cron.sh for Railway Cron.

set -e

echo "Starting Laravel Scheduler Service..."

bash ./railway/install-postgres-client.sh

echo "Verifying scheduler configuration..."
php artisan schedule:list --quiet 2>/dev/null || true

echo "Starting Laravel schedule worker..."
exec php artisan schedule:work
