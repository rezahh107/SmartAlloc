## Summary
<!-- What changed? Why? -->

## QA Checklist (advisory)
- [ ] Ran `composer qa:advisory`
- [ ] If i18n touched, ran `php scripts/pot-refresh.php`
- [ ] REST routes enforce cap + nonce/signature
- [ ] No unprepared `$wpdb` queries
- [ ] Linked rehearsal summary/JUnit and attached artifacts

## Artifacts
- `artifacts/ga/GA_REHEARSAL.txt`
- `artifacts/ga/GA_REHEARSAL.junit.xml`
