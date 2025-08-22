# Distribution Audit

`dist-build.php` assembles the release package under `dist/SmartAlloc/`.
Only production files are copied. Excluded paths:

- `.git/`
- `node_modules/`
- `vendor/*dev*/`
- `tests/`
- `.github/`
- `artifacts/`
- `coverage/`
- `*.md` files except `readme.txt`

Files and directories are copied in deterministic sorted order. Text files
have their line endings normalised to LF. All files are set to `0644` and
directories to `0755`.

`dist-audit.php` inspects the built package and emits
`artifacts/dist/audit.json` containing any advisory warnings such as
unexpected developer artefacts, missing plugin headers or readme, and
text‑domain inconsistencies.
