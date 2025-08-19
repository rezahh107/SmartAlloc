#!/bin/bash
set -euo pipefail

# Wait for database to be ready
until wp db check >/dev/null 2>&1; do
  echo 'Waiting for database...'
  sleep 3
done

wp core install \
  --url="http://localhost:8080" \
  --title="SmartAlloc Test" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email

wp user create editor editor@example.com --role=editor --user_pass=editor

wp plugin activate smart-alloc
wp smartalloc upgrade

wp eval-file /var/www/html/wp-content/plugins/smart-alloc/e2e/seed.php
