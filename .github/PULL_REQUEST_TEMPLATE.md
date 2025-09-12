## Summary
<!-- What changed? Why? -->



<!-- CI-CHECKLIST:BEGIN -->
## CI Checklist
- [ ] CI passes on this PR (phpunit / quality:selective / baseline-check)
- [ ] No application code changed (infra-only)
- [ ] Coverage meets project target (≥80% if applicable)
- [ ] Baseline phase: FOUNDATION — PASS
- [ ] `.env` not committed; `vendor/` and `wp-content/uploads/` ignored
<!-- CI-CHECKLIST:END -->## QA Checklist (advisory)
- [ ] Ran `composer qa:advisory`
- [ ] If i18n touched, ran `php scripts/pot-refresh.php`
- [ ] REST routes enforce cap + nonce/signature
- [ ] No unprepared `$wpdb` queries
- [ ] Linked rehearsal summary/JUnit and attached artifacts

## Artifacts
- `artifacts/ga/GA_REHEARSAL.txt`
- `artifacts/ga/GA_REHEARSAL.junit.xml`

## Local Mirrors
```bash
make -f Makefile.docker docker-test   # DB up → init → phpunit
make -f Makefile.docker docker-ci     # selective gates + baseline
```
