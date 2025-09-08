#!/bin/bash
set -e
vendor/bin/phpcs --standard=WordPress-Extra includes/ admin/ smart-alloc.php
vendor/bin/phpstan analyze --no-progress
WP_CLI_PACKAGES_DIR=/tmp/wp-test wp plugin check ./ --allow-root --format=table
vendor/bin/phpunit --group smoke --no-coverage
php baseline-check --current-phase=FOUNDATION
php baseline-compare --feature=post-merge-fixes
php gap-analysis --target=baseline
