# Performance Testing (SmartAlloc)

## Budgets (env)
- SMARTALLOC_BUDGET_ALLOC_1K_MS=2500
- SMARTALLOC_BUDGET_ALLOC_10K_MS=12000
- SMARTALLOC_BUDGET_Q_1K=2000
- SMARTALLOC_BUDGET_Q_10K=12000
- SMARTALLOC_PERF_ENABLE_CACHE=0|1
- SMARTALLOC_PERF_ENABLE_BATCH=0|1

`scripts/update_state.sh` runs a Stopwatch scenario and deducts points when `SMARTALLOC_BUDGET_ALLOC_1K_MS` is exceeded.

## Run
composer dump-autoload -o
SMARTALLOC_TESTS=1 vendor/bin/phpunit --testsuite Performance
SMARTALLOC_TESTS=1 vendor/bin/phpunit --testsuite Regression

## Notes
- QueryPlanGuard fails on linear query growth (~N+1). Use batching/caching flags for diagnostics; outputs must remain identical.
