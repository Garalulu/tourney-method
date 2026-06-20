#!/bin/bash

##############################################################################
# Laravel Sail Permission Fix Script
#
# Fixes permission issues caused by running Docker commands as root user
# instead of the sail user. This is common on Windows with Git Bash.
#
# Usage: bash fix-permissions.sh
##############################################################################

echo "🔧 Fixing Laravel Sail permissions..."
echo ""

# Fix ownership for common directories that get root-owned files
echo "Step 1: Fixing file ownership..."
MSYS_NO_PATHCONV=1 docker compose exec -u root laravel.test chown -R sail:sail \
  //var/www/html/storage \
  //var/www/html/bootstrap/cache \
  //var/www/html/node_modules

if [ $? -eq 0 ]; then
  echo "✅ Ownership fixed"
else
  echo "❌ Failed to fix ownership"
  exit 1
fi

echo ""

# Clear all caches
echo "Step 2: Clearing all caches..."
docker compose exec -u sail laravel.test php artisan cache:clear
docker compose exec -u sail laravel.test php artisan view:clear
docker compose exec -u sail laravel.test php artisan config:clear

echo ""
echo "✅ Permissions fixed! Caches cleared."
echo ""
echo "💡 To prevent this issue, always use:"
echo "   - sail npm run dev"
echo "   - sail php artisan [command]"
echo "   - docker compose exec -u sail laravel.test [command]"
echo ""
echo "❌ Avoid:"
echo "   - docker compose exec -u sail laravel.test npm run dev"
echo "   - docker compose exec -u sail laravel.test php artisan [command]"
