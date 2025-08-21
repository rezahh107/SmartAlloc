#!/usr/bin/env bash

set +e
MANIFEST="$(dirname "$(dirname "$0")")/artifacts/dist/manifest.json"
DIST_DIR="$(dirname "$MANIFEST")"
KEY="${MANIFEST_SIGN_KEY:-}"

if [ ! -f "$MANIFEST" ]; then
  echo "manifest.json not found; skipping" >&2
  exit 0
fi

if ! command -v openssl >/dev/null 2>&1; then
  echo "openssl not available; skipping" >&2
  exit 0
fi

HASH_FILE="$DIST_DIR/manifest.sha256"
SIG_FILE="$DIST_DIR/manifest.sig"
ASC_FILE="$DIST_DIR/manifest.asc"

hash=$(openssl dgst -sha256 -r "$MANIFEST" 2>/dev/null | awk '{print $1}')
if [ -n "$hash" ]; then
  echo "$hash" > "$HASH_FILE"
fi

if [ -n "$KEY" ] && [ -f "$KEY" ]; then
  openssl dgst -sha256 -sign "$KEY" -out "$SIG_FILE" "$MANIFEST" 2>/dev/null
  if command -v gpg >/dev/null 2>&1; then
    gpg --batch --yes --armor --detach-sign -o "$ASC_FILE" "$MANIFEST" 2>/dev/null || true
  else
    echo "gpg not available; skipping ascii signature" >&2
  fi
else
  echo "signing key missing; skipping signatures" >&2
fi

exit 0
