#!/bin/sh
# Runs on every `docker compose up`, before dependent services start.
#   1. Fresh database  -> load schema (storage/db/schema.sql)
#   2. Every start     -> dbmate up (apply pending migrations)
#   3. First start     -> run storage/db/init.sql once (seed data), tracked by a marker table
#   4. Every start     -> create/update the limited app role (SELECT/INSERT/UPDATE/DELETE only)
set -eu

log() { echo "[migrate] $*"; }
psql_q() { psql "$DATABASE_URL" -X -At -v ON_ERROR_STOP=1 "$@"; }

: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${POSTGRES_APP_USER:?POSTGRES_APP_USER is required}"
: "${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD is required}"
if [ "$POSTGRES_APP_USER" = "$POSTGRES_USER" ]; then
  echo "[migrate] ERROR: POSTGRES_APP_USER must differ from POSTGRES_USER (superuser)" >&2
  exit 1
fi

log "Waiting for database..."
dbmate wait

# 1. Fresh database? (dbmate's tracking table doesn't exist yet)
if [ "$(psql_q -c "SELECT to_regclass('schema_migrations') IS NULL")" = "t" ]; then
  log "Fresh database detected - loading schema"
  dbmate load
fi

# 2. Apply any migrations that are not applied yet
log "Applying pending migrations"
dbmate up

# 3. One-time seed data (init.sql runs only until the marker table exists;
#    the marker is created in the same transaction, so a failed init retries next start)
if [ "$(psql_q -c "SELECT to_regclass('public.app_bootstrap') IS NULL")" = "t" ]; then
  if [ -f /storage/db/init.sql ]; then
    log "First start - running init.sql"
    : "${ADMIN_USERNAME:?ADMIN_USERNAME is required for the first start}"
    : "${ADMIN_EMAIL:?ADMIN_EMAIL is required for the first start}"
    : "${ADMIN_PASS_HASH:?ADMIN_PASS_HASH is required for the first start}"
    psql "$DATABASE_URL" -X -q -v ON_ERROR_STOP=1 --single-transaction \
      -v admin_username="$ADMIN_USERNAME" \
      -v admin_email="$ADMIN_EMAIL" \
      -v admin_pass_hash="$ADMIN_PASS_HASH" \
      -f /storage/db/init.sql \
      -c "CREATE TABLE public.app_bootstrap (initialized_at timestamptz NOT NULL DEFAULT now()); INSERT INTO public.app_bootstrap DEFAULT VALUES;"
  else
    log "WARNING: /storage/db/init.sql not found - skipping seed step"
  fi
else
  log "init.sql already applied - skipping"
fi

# 4. Limited application role. Runs on every start (idempotent) so that
#    password changes in .env and newly migrated tables are picked up.
log "Configuring application role '$POSTGRES_APP_USER'"
psql "$DATABASE_URL" -X -q -v ON_ERROR_STOP=1 \
  -v admin_user="$POSTGRES_USER" \
  -v app_user="$POSTGRES_APP_USER" \
  -v app_pass="$POSTGRES_APP_PASSWORD" \
  -v db_name="$POSTGRES_DB" <<'SQL'
-- create the role if missing
SELECT format('CREATE ROLE %I', :'app_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user')
\gexec

-- (re)apply login, password and strip any elevated attributes
ALTER ROLE :"app_user" WITH LOGIN PASSWORD :'app_pass'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

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
SELECT format('REVOKE ALL ON TABLE %s FROM %I', t, :'app_user')
FROM unnest(ARRAY['public.schema_migrations', 'public.app_bootstrap']) AS t
WHERE to_regclass(t) IS NOT NULL
\gexec
SQL

log "Done - database is ready"