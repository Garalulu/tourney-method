#!/bin/bash
# Railway Worker Service - Laravel Horizon Startup Script
# This script starts the Laravel Horizon queue worker process

set -e

echo "Starting Laravel Horizon Worker Service..."

# Ensure Horizon is properly configured
echo "Verifying Horizon configuration..."
php artisan horizon:install --quiet 2>/dev/null || true

# Start Horizon
echo "Starting Horizon..."
php artisan horizon
