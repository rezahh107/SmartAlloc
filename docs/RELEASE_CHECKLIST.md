# Release Checklist

1. `php scripts/dist-build.php`
2. `php scripts/dist-manifest.php`
3. `php scripts/dist-audit.php`
4. `php scripts/version-coherence.php`
5. `php scripts/validate-readme.php`
6. `php scripts/sbom-from-composer.php`
7. `php scripts/tag-preflight.php`
8. `bash scripts/tag-dry-run.sh`

Inspect artifacts under `artifacts/dist/` and `artifacts/release/` for any
warnings. `tag-preflight.php --enforce` exits non‑zero when blocking
warnings are present.
