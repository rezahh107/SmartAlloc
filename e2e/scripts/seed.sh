set -euo pipefail
WP="${WP_CLI:-docker compose exec -T wordpress wp}"
$WP core is-installed || $WP core install --url="${WP_BASE_URL:-http://localhost:8080}" --title="E2E" --admin_user=admin --admin_password=admin --admin_email=admin@example.com
$WP plugin activate smart-alloc || true
$WP user create reviewer reviewer@example.com --role=editor --user_pass=reviewer || true
$WP eval-file wp-content/plugins/smart-alloc/e2e/scripts/seed-data.php
