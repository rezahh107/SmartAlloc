# QA Bundle

`scripts/qa-bundle.php` assembles a selection of QA artifacts into a
single ZIP archive for release reviews. The bundle is written to
`artifacts/qa/qa-bundle.zip`.

Contents
--------

The archive contains the following files (placeholders are inserted if a
file is missing):

- `schema-validate.json`
- `coverage.json`
- `sql-prepare.json`
- `rest-permissions.json`
- `secrets.json`
- `license-audit.json`
- `qa-report.json`
- `qa-report.html`

Usage
-----

Run locally with:

```sh
php scripts/qa-bundle.php
```

The resulting ZIP can be shared with reviewers or attached to release
artifacts.

