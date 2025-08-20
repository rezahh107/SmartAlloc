# Nightly (Opt-in, Non-blocking)

Use locally or in a separate CI that is NOT required:
- Security (static heuristics): `RUN_SECURITY_TESTS=1 vendor/bin/phpunit --filter "NonceVerification|SQLInjection"`
- Performance budget: `RUN_PERFORMANCE_TESTS=1 vendor/bin/phpunit --filter RequestBudget`
- E2E (Playwright): `E2E=1 BASE_URL=http://localhost:8889 npx playwright test`
Or run helper: `bash scripts/nightly-local.sh`

All checks are SKIP-safe and do not affect the default CI.

