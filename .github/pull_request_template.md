## Summary
<!-- What changed? Why? -->

## QA Checklist (advisory)
- [ ] Ran `composer qa:advisory` locally (coverage import + schema validate + GA Enforcer RC)
- [ ] If i18n touched: ran `php scripts/pot-refresh.php`
- [ ] If UI touched: ran `E2E=1 E2E_RTL=1 npx playwright test tests/e2e/rtl-snapshot.spec.ts`
- [ ] No unprepared `$wpdb` queries; REST routes have non-trivial `permission_callback`

## Artifacts (links if available)
- Coverage: `artifacts/coverage/coverage.json`
- Schema: `artifacts/schema/schema-validate.json`
- GA Enforcer: `artifacts/ga/…`
