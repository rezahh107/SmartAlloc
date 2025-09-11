#!/usr/bin/env bash
set -euo pipefail

log(){ echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) [setup] $*"; }

log "Starting services"
docker compose up -d

log "Initializing database"
./scripts/db-init.sh

log "Installing WordPress if needed"
if ! docker compose run --rm wordpress wp core is-installed >/dev/null 2>&1; then
  docker compose run --rm wordpress wp core install \
    --url="http://localhost:8080" \
    --title="SmartAlloc" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@example.org"
fi

log "Activating SmartAlloc plugin"
docker compose run --rm wordpress wp plugin is-active smart-alloc >/dev/null 2>&1 || \
  docker compose run --rm wordpress wp plugin activate smart-alloc

log "Setup complete"
