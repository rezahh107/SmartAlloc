# Contributing to SmartAlloc

## Quick start
1) `composer install`
2) Lint: `vendor/bin/phpcs -q --report=summary`
3) Tests: `vendor/bin/phpunit --testdox --colors=never`
4) Status pack: `bash scripts/status-pack.sh`

## 5D quality gates
Security ≥20, Logic ≥16, Performance ≥16, Readability ≥18, Goal ≥12, Weighted ≥85%.

## CI notes
- `ga-enforce` fails PR if `.ci_failure` exists or gates fail.
- Keep UTC in logs/DB; use capabilities and nonces.
