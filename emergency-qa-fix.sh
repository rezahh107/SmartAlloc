#!/bin/bash
set -euo pipefail
echo "Emergency QA Fix"
composer install --no-interaction --prefer-dist --optimize-autoloader >/dev/null 2>&1 || true
scripts/fix-phpstan-fatal.sh || true
scripts/fix-phpcs-hang.sh || true
scripts/install-wp-cli.sh || true
echo "PHPCS:" && vendor/bin/phpcs --version
echo "PHPStan:" && vendor/bin/phpstan --version
echo "WP-CLI:" && { wp --version --allow-root 2>/dev/null || ./wp --version --allow-root; }
echo "PHPUnit:" && vendor/bin/phpunit --version
