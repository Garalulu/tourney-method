#!/bin/bash
# Railway Cron Service - short-lived Laravel command runner.
# Railway Cron should invoke this script with a command, then the service exits.

set -e

echo "Starting Laravel Cron Command Service..."

bash ./railway/install-postgres-client.sh

verify_postgres_client() {
    binary="$1"

    if ! command -v "$binary" >/dev/null 2>&1; then
        echo "ERROR: $binary is not installed. Install PostgreSQL 18 client in the Railway runtime image."
        exit 1
    fi

    version="$("$binary" --version)"
    echo "$version"

    case "$version" in
        *" 18."*|*"(PostgreSQL) 18"*) ;;
        *)
            echo "ERROR: $binary must be PostgreSQL 18.x for scheduler backup compatibility."
            exit 1
            ;;
    esac
}

echo "Verifying PostgreSQL client tools..."
verify_postgres_client pg_dump
verify_postgres_client psql

if [ "$#" -gt 0 ]; then
    echo "Running cron command from arguments: $*"
    exec "$@"
fi

if [ -n "${RAILWAY_CRON_COMMAND:-}" ]; then
    echo "Running cron command from RAILWAY_CRON_COMMAND: ${RAILWAY_CRON_COMMAND}"
    exec bash -lc "${RAILWAY_CRON_COMMAND}"
fi

echo "No explicit cron command provided; running due Laravel schedule once."
php artisan schedule:list --quiet 2>/dev/null || true
exec php artisan schedule:run
