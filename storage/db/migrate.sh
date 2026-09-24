#!/bin/sh
# Runs on every `docker compose up`, before dependent services start.
#
#   First start (once):
#     1. load schema            storage/db/schema.sql
#     2. dbmate up              (only if migration files exist)
#     3. init.sql               admin user, genres, categories
#     4. test_data.sql          (only if SEED_TEST_DATA=true)
#     5. create the limited app role (SELECT/INSERT/UPDATE/DELETE only)
#   Every later start:
#     dbmate up                 apply new migrations, if any
set -eu

log() { echo "[migrate] $*"; }
psql_q() { psql "$DATABASE_URL" -X -At -v ON_ERROR_STOP=1 "$@"; }

: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${POSTGRES_DB:?POSTGRES_DB is required}"

log "Waiting for database..."
dbmate wait

# 1. Fresh database? (no tables in the public schema yet)
if [ "$(psql_q -c "SELECT NOT EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind IN ('r','p'))")" = "t" ]; then
  log "Fresh database detected - loading schema"
  dbmate load
fi

# 2. Apply migrations (skipped while there are no migration files)
if ls "$DBMATE_MIGRATIONS_DIR"/*.sql >/dev/null 2>&1; then
  log "Applying pending migrations"
  dbmate up
else
  log "No migration files in $DBMATE_MIGRATIONS_DIR - skipping dbmate up"
fi

# 3-5. First start only. Everything below runs in ONE transaction together with the
#      marker table, so if any step fails nothing is kept and the next start retries.
if [ "$(psql_q -c "SELECT to_regclass('public.app_bootstrap') IS NULL")" = "t" ]; then
  log "First start - seeding data and creating the application role"

  : "${ADMIN_USERNAME:?ADMIN_USERNAME is required for the first start}"
  : "${ADMIN_EMAIL:?ADMIN_EMAIL is required for the first start}"
  : "${ADMIN_PASS_HASH:?ADMIN_PASS_HASH is required for the first start}"
  : "${POSTGRES_APP_USER:?POSTGRES_APP_USER is required for the first start}"
  : "${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD is required for the first start}"
  if [ "$POSTGRES_APP_USER" = "$POSTGRES_USER" ]; then
    echo "[migrate] ERROR: POSTGRES_APP_USER must differ from POSTGRES_USER (superuser)" >&2
    exit 1
  fi
  if [ ! -f /storage/db/init.sql ]; then
    echo "[migrate] ERROR: /storage/db/init.sql not found" >&2
    exit 1
  fi

  # SQL files to run, in order
  set -- -f /storage/db/init.sql
  if [ "${SEED_TEST_DATA:-false}" = "true" ]; then
    if [ -f /storage/db/test_data.sql ]; then
      log "SEED_TEST_DATA=true - test data will be inserted"
      set -- "$@" -f /storage/db/test_data.sql
    else
      log "WARNING: /storage/db/test_data.sql not found - skipping test data"
    fi
  fi

  # Application role (runs last, after the marker table exists)
  cat > /tmp/app_role.sql <<'SQL'
-- create the role if missing
SELECT format('CREATE ROLE %I', :'app_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user')
\gexec

ALTER ROLE :"app_user" WITH LOGIN PASSWORD :'app_pass'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

-- dbmate's tracking table must exist now so it can be locked away from the app role
CREATE TABLE IF NOT EXISTS public.schema_migrations (version character varying(128) PRIMARY KEY);

-- connect + read/write data only (no DDL)
GRANT CONNECT ON DATABASE :"db_name" TO :"app_user";
GRANT USAGE ON SCHEMA public TO :"app_user";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app_user";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app_user";

-- tables/sequences created by future migrations (run as the superuser) get the same grants
ALTER DEFAULT PRIVILEGES FOR ROLE :"admin_user" IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES FOR ROLE :"admin_user" IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";

-- keep bookkeeping tables away from the app role
REVOKE ALL ON TABLE public.schema_migrations, public.app_bootstrap FROM :"app_user";
SQL

  psql "$DATABASE_URL" -X -q -v ON_ERROR_STOP=1 --single-transaction \
    -v admin_username="$ADMIN_USERNAME" \
    -v admin_email="$ADMIN_EMAIL" \
    -v admin_pass_hash="$ADMIN_PASS_HASH" \
    -v admin_user="$POSTGRES_USER" \
    -v app_user="$POSTGRES_APP_USER" \
    -v app_pass="$POSTGRES_APP_PASSWORD" \
    -v db_name="$POSTGRES_DB" \
    "$@" \
    -c "CREATE TABLE public.app_bootstrap (initialized_at timestamptz NOT NULL DEFAULT now()); INSERT INTO public.app_bootstrap DEFAULT VALUES;" \
    -f /tmp/app_role.sql

  log "First-start setup finished"
else
  log "Already set up - only migrations were checked"
fi

log "Done - database is ready"