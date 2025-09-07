#!/bin/bash
echo "Quick QA Test"
vendor/bin/phpcs --version
vendor/bin/phpstan --version
wp --version --allow-root 2>/dev/null || ./wp --version --allow-root
vendor/bin/phpunit --version
./scripts/patch-guard-check.sh
