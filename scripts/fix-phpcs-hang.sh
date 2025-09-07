#!/bin/bash
set -euo pipefail
echo "Fixing PHPCS"
rm -rf ~/.phpcs.cache .phpcs-cache
vendor/bin/phpcs --config-set report_width 120
vendor/bin/phpcs --config-set colors 1
vendor/bin/phpcs --config-set show_progress 1
echo "<?php echo 'test';" > /tmp/sa-phpcs.php
timeout 30s vendor/bin/phpcs --standard=WordPress-Extra /tmp/sa-phpcs.php
rm /tmp/sa-phpcs.php
