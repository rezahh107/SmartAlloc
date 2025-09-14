#!/usr/bin/env bash
set -euo pipefail
HOST="${1:-127.0.0.1}"
USER="${2:-root}"
PASS="${3:-root}"
for i in {1..60}; do
  if mysqladmin ping -h "$HOST" -u"$USER" -p"$PASS" --silent; then
    exit 0
  fi
  sleep 2
done
echo "DB not ready after waiting." >&2
exit 1
