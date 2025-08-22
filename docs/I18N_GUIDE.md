# I18N Guide

The plugin uses the text domain **smartalloc**.  All user facing strings in PHP
or JavaScript must be wrapped in one of WordPress' translation helpers such as
`__()`, `_e()`, `_x()` or their escaping variants.  Placeholder arguments must
be consistently numbered (e.g. `%1$s %2$s`) or unnumbered (e.g. `%s %s`).

### JavaScript
Use the `wp.i18n` package: `wp.i18n.__('My string', 'smartalloc');`.

### Tooling

```bash
php scripts/i18n-lint.php         # reports domain and placeholder issues
php scripts/pot-refresh.php       # regenerates languages/smartalloc.pot
php scripts/pot-diff.php          # compares source strings to the POT
```

These commands produce JSON reports under `artifacts/i18n/`.
