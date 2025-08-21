# GA Rehearsal

The GA rehearsal script aggregates the release QA artifacts into a single offline bundle.
It runs only local tools and skips any missing step.

## Quick start

```bash
bash scripts/ga-rehearsal.sh
RUN_GA_REHEARSAL=1 vendor/bin/phpunit --filter GARehearsalSmokeTest

# after rehearsal you may run the GA enforcer to apply thresholds
RUN_ENFORCE=1 php scripts/ga-enforcer.php --enforce
```

## Artifact map

| QA Plan stage | Artifact/Action |
| ------------- | --------------- |
| 2 | `bash scripts/qa-orchestrator.sh` |
| 3 | `bash scripts/release-finalizer.sh` |
| 4 | `php scripts/wporg-svn-prepare.php` (trunk/tags/assets under `artifacts/wporg/`) |
| 7 | `php scripts/wporg-changelog-truncate.php` |
| 9 | `php scripts/wporg-deploy-checklist.php` |
| 14 | `php scripts/qa-index.php` + `php scripts/qa-bundle.php` |
| 15 | `php scripts/go-no-go.php` + `php scripts/release-notes.php` |

## Rehearsal vs finalizer

`ga-rehearsal.sh` calls `release-finalizer.sh` but also performs a WP.org dry run
(`wporg-svn-prepare.php`, changelog truncation and deploy checklist) so the team can
review everything in one place before GA.
