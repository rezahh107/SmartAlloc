# WP.org Deploy

The WordPress.org release package lives in a `wporg/` directory with the
following layout:

```
wporg/
  trunk/          # plugin source copied from dist/SmartAlloc
  assets/         # banners, icons, screenshots
  tags/<version>/ # created empty for tagging
```

Required asset files:

- `banner-1544x500.(png|jpg)`
- `banner-772x250.(png|jpg)`
- `icon-256x256.(png|jpg)`
- `icon-128x128.(png|jpg)`

All files must use Unix LF line endings and reasonable permissions.

## CLI helpers

```bash
php scripts/wporg-svn-prepare.php
php scripts/wporg-changelog-truncate.php
php scripts/wporg-deploy-checklist.php
```

The checklist script emits `artifacts/wporg/deploy-checklist.json` containing
any warnings about missing assets, bad sizes or Stable tag mismatches.
