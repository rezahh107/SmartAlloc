# I18N Checklist

- [ ] Ensure all translation calls use the `smartalloc` text domain.
- [ ] Verify printf-style placeholders are numbered and safe for translators.
- [ ] Refresh `languages/smartalloc.pot` and review differences.
- [ ] Audit RTL styles for visual regressions.
- [ ] Review `.wordpress-org` assets (icons, banners, screenshots) for expected sizes.

See [DEPLOY_WPORG.md](./DEPLOY_WPORG.md) and [RELEASE_GATE.md](./RELEASE_GATE.md) for full release guidance.

## How to run

CLI checks:

```bash
php scripts/i18n-lint.php > i18n-lint.json
php scripts/pot-diff.php > pot-diff.json
php scripts/wporg-assets-verify.php | tail -n 1 > wporg-assets.json
```

E2E smoke:

```bash
E2E=1 E2E_I18N=1 npx playwright test tests/e2e/i18n-ui.spec.ts
```

## QA Plan Mapping

| Check | QA Plan Stage |
| ----- | ------------- |
| i18n lint | 4 (Persian/RTL), 7 (Gutenberg) |
| pot diff | 4 (Persian/RTL), 7 (Gutenberg) |
| wporg assets verify | 4 (Persian/RTL) |
| i18n E2E smoke | 4 (Persian/RTL) |
