#!/usr/bin/env bash
# bootstrap_complete.sh - SmartAlloc emergency recovery
set -euo pipefail

BLUE='\033[0;34m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
log(){ echo -e "${BLUE}[$(date +'%H:%M:%S')] $1${NC}"; }
ok(){ echo -e "${GREEN}✔ $1${NC}"; }
warn(){ echo -e "${YELLOW}⚠ $1${NC}"; }
err(){ echo -e "${RED}✖ $1${NC}"; }

log 'Checking prerequisites'
command -v php >/dev/null 2>&1 || { err 'PHP not found'; exit 1; }
command -v composer >/dev/null 2>&1 || { err 'Composer not found'; exit 1; }
ok 'Prerequisites ok'

log 'Installing dependencies'
composer install --no-interaction --prefer-dist >/dev/null && ok 'Dependencies installed'

log 'Configuring PHPCS'
vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs >/dev/null 2>&1 || true

log 'Running baseline checks'
php scripts/baseline-check.php --current-phase=FOUNDATION >/dev/null 2>&1 || warn 'baseline-check issues'
php scripts/baseline-compare.php --feature=bootstrap-complete >/dev/null 2>&1 || warn 'baseline-compare issues'
php scripts/gap-analysis.php --target=baseline >/dev/null 2>&1 || warn 'gap-analysis issues'

log 'Quality tools'
if vendor/bin/phpcs --standard=WordPress-Extra --extensions=php ./ >/dev/null 2>&1; then ok 'PHPCS clean'; else warn 'PHPCS issues'; fi
if vendor/bin/phpstan analyze --level=5 includes/ >/dev/null 2>&1; then ok 'PHPStan clean'; else warn 'PHPStan issues'; fi
if vendor/bin/phpunit --group smoke --no-coverage >/dev/null 2>&1; then ok 'PHPUnit smoke pass'; else warn 'PHPUnit smoke fail'; fi
if scripts/patch-guard-check.sh >/dev/null 2>&1; then ok 'Patch Guard pass'; else warn 'Patch Guard fail'; fi

ok 'Bootstrap complete'
