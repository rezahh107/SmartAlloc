#!/usr/bin/env bash
set -euo pipefail

log(){ echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) [sanity] $*"; }

log "PHP version $(php -r 'echo PHP_VERSION;')"

TZ=$(php -r 'echo date_default_timezone_get();')
if [ "$TZ" != "UTC" ]; then
  log "timezone check failed: $TZ"
  exit 1
fi
log "timezone UTC confirmed"
