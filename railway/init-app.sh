#!/bin/bash
# Railway App Service - Pre-Deploy Initialization Script
# This script runs after the build completes but before the app starts

set -e

echo "Initializing Tourney Method App Service..."

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear and cache configurations
echo "Caching configuration..."
php artisan config:cache

# Cache routes
echo "Caching routes..."
php artisan route:cache

# Cache views
echo "Caching views..."
php artisan view:cache

echo "App service initialization complete!"
