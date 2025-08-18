#!/usr/bin/env bash
set -euo pipefail

WP_VERSION="${WP_VERSION:-latest}"
WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

wp core download --path="$WORKDIR/wordpress" --version="$WP_VERSION" --skip-content >/dev/null
cp -R "$(pwd)" "$WORKDIR/wordpress/wp-content/plugins/smart-alloc"
wp plugin install plugin-check --path="$WORKDIR/wordpress" --activate --quiet
wp plugin activate smart-alloc --path="$WORKDIR/wordpress" --quiet
wp plugin check smart-alloc --path="$WORKDIR/wordpress" "$@"
