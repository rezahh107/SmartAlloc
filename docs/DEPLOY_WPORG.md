# WordPress.org Deploy (Dry-Run)

The toolkit under `scripts/` prepares an offline SVN layout and supporting files. It does **not** perform any network operations.

## Preparation

1. Normalize your build and ensure dist artifacts exist (e.g. `artifacts/dist/SmartAlloc-normalized.zip`).
2. Build the SVN tree:
   ```bash
   php scripts/wporg-svn-prepare.php artifacts/dist/SmartAlloc-normalized.zip 1.0.0
   ```
3. Truncate the changelog to the latest entries:
   ```bash
   php scripts/wporg-changelog-truncate.php 3
   ```
4. Generate the deployment checklist:
   ```bash
   php scripts/wporg-deploy-checklist.php
   ```

Artifacts are written to `artifacts/wporg/` and are safe to inspect offline.

## Manual SVN Steps

1. From a local checkout of the plugin SVN repository:
   ```bash
   svn cp trunk tags/1.0.0
   ```
2. Copy the prepared `trunk/`, `tags/1.0.0/`, and `assets/` from `artifacts/wporg/` into the SVN checkout.
3. Upload assets and commit:
   ```bash
   svn add --force .
   svn commit -m "Release 1.0.0"
   ```
4. Verify `readme.txt` and changelog using the truncated file.
5. Compare checksums from `DEPLOY_CHECKLIST.md` before uploading the final ZIP.
6. Consult [OPS_TRIAGE.md](OPS_TRIAGE.md) for any last-minute issues.
7. After release, follow [POST_RELEASE.md](POST_RELEASE.md).

The toolkit is advisory and intended for offline verification only.
