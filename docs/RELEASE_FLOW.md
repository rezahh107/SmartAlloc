# Release Flow

This document describes the steps to cut a release candidate (RC) and promote it to a general availability (GA) release.

## RC
1. `composer changelog` – update `CHANGELOG.md` from commits.
2. `composer run bump vX.Y.Z-rc.N` – bump versions in plugin, readme and changelog.
3. `composer release:rc -- --tag=vX.Y.Z-rc.N` – run `scripts/tag-release.php` in advisory mode. Artifacts are written to `artifacts/release/`.
4. Review the artifacts and release notes.

## GA
1. Ensure `readme.txt` stable tag matches `vX.Y.Z`.
2. `composer run bump vX.Y.Z` – promote changelog and update stable tag.
3. `composer release:ga -- --tag=vX.Y.Z` – enforces GA checks and generates signed checksums.
4. `php scripts/wporg-stage.php vX.Y.Z` – stage files for WordPress.org. Inspect `/trunk` and `/assets` for correctness.
5. Create a lightweight git tag `vX.Y.Z` if not already created.
6. Upload artifacts in `artifacts/release/vX.Y.Z/` to the release page and submit to WordPress.org SVN.

## Failure Handling
- RC tasks are advisory; investigate warnings but the command exits successfully.
- GA runs with `--enforce=true` and stops on any warning listed in `artifacts/ga/GA_READY.txt`.
- If a step fails, fix the underlying issue and re-run the command.
