#!/bin/bash
# Install PostgreSQL 18 client tools for Railway cron services.
# This is a runtime fallback for Railpack images where PGDG packages were not
# baked into the final image.

set -euo pipefail

required_major="18"

postgres_client_is_ready() {
    command -v pg_dump >/dev/null 2>&1 \
        && command -v psql >/dev/null 2>&1 \
        && pg_dump --version | grep -Eq "(${required_major}\.|PostgreSQL\\) ${required_major})" \
        && psql --version | grep -Eq "(${required_major}\.|PostgreSQL\\) ${required_major})"
}

if postgres_client_is_ready; then
    echo "PostgreSQL ${required_major} client tools already installed."
    exit 0
fi

if ! command -v apt-get >/dev/null 2>&1; then
    echo "ERROR: apt-get is unavailable; install PostgreSQL ${required_major} client in the Railway runtime image."
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: PostgreSQL ${required_major} client is missing and the container is not running as root."
    echo "Set RAILPACK_DEPLOY_APT_PACKAGES as a Railway service variable or switch to a custom Dockerfile."
    exit 1
fi

echo "Installing PostgreSQL ${required_major} client tools..."

export DEBIAN_FRONTEND=noninteractive

apt-get update -qq
apt-get install -y -qq --no-install-recommends curl ca-certificates postgresql-common

install -d /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc

. /etc/os-release
debian_codename="${VERSION_CODENAME:-bookworm}"

echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${debian_codename}-pgdg main" \
    > /etc/apt/sources.list.d/pgdg.list

apt-get update -qq
apt-get install -y -qq --no-install-recommends postgresql-client-${required_major} libpq-dev
apt-get clean
rm -rf /var/lib/apt/lists/*

pg_dump --version
psql --version
