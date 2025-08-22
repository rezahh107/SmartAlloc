# Performance Benchmarks

Run allocation benchmarks and query plan guard checks:

```
composer dump-autoload -o
vendor/bin/phpunit --testsuite Performance
vendor/bin/phpunit --testsuite Regression
```

Environment budgets (defaults shown):

- `SMARTALLOC_BUDGET_ALLOC_1K_MS=2500`
- `SMARTALLOC_BUDGET_ALLOC_10K_MS=12000`
- `SMARTALLOC_BUDGET_Q_1K=2000`
- `SMARTALLOC_BUDGET_Q_10K=12000`

Set these variables to loosen or tighten limits in CI. `QueryPlanGuardTest` fails when query counts grow suspiciously with dataset size.

Feature flags `SMARTALLOC_PERF_ENABLE_CACHE` and `SMARTALLOC_PERF_ENABLE_BATCH` toggle optional optimizations; allocation results must remain identical regardless of flag values.
