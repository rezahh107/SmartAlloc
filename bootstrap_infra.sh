#!/usr/bin/env bash
set -euo pipefail

# Ensure composer.json exists with minimal config if absent
if [[ ! -f composer.json ]]; then
  cat > composer.json <<'JSON'
{
  "name": "smartalloc/dev-bootstrap",
  "require-dev": {
    "phpunit/phpunit": "^10.5",
    "squizlabs/php_codesniffer": "^3.9",
    "wp-coding-standards/wpcs": "^3.0"
  }
}
JSON
fi

# Install composer dependencies
composer install --no-interaction --prefer-dist

# Configure PHPCS to know about WPCS
if vendor/bin/phpcs --config-show installed_paths | grep -q wpcs; then
  echo "PHPCS installed_paths already configured"
else
  vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
fi

# Setup pre-commit hook
HOOK_FILE=".git/hooks/pre-commit"
if [[ ! -f "$HOOK_FILE" ]]; then
  cat > "$HOOK_FILE" <<'HOOK'
#!/usr/bin/env bash
set -e

CHANGED=$(git diff --cached --name-only -- '*.php' '*.sh')
if [[ -n "$CHANGED" ]]; then
  vendor/bin/phpcs $CHANGED
fi

vendor/bin/phpunit tests/Smoke/ToolsSmokeTest.php --group smoke
HOOK
  chmod +x "$HOOK_FILE"
fi

echo "Infrastructure bootstrap complete"
