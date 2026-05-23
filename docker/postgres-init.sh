#!/bin/sh
# Runs once, the first time the postgres data volume is initialised. We
# pre-create the app_test database so the integration tests have a private
# schema and never have to touch the main `app` database that the running
# application uses. Subsequent boots find the database already present and
# this script is not re-executed.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-SQL
    CREATE DATABASE app_test;
SQL
