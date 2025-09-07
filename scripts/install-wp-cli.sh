#!/bin/bash
set -euo pipefail
echo "Installing WP-CLI"
if command -v wp >/dev/null 2>&1; then
  wp --version --allow-root
  exit 0
fi
curl -sS -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
if [ -w /usr/local/bin ]; then
  sudo mv wp-cli.phar /usr/local/bin/wp
  wp --version --allow-root
else
  mv wp-cli.phar ./wp
  ./wp --version --allow-root
fi
