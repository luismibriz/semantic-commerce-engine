#!/bin/sh
set -e

echo "[entrypoint] Waiting for PostgreSQL..."
until php bin/console dbal:run-sql 'SELECT 1' >/dev/null 2>&1; do
    sleep 2
done

echo "[entrypoint] Applying database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[entrypoint] Ensuring the search index exists..."
until php bin/console search:setup >/dev/null 2>&1; do
    echo "[entrypoint] Search backend not ready yet, retrying..."
    sleep 3
done

echo "[entrypoint] Startup complete — handing over to the web server."
exec docker-php-entrypoint "$@"
