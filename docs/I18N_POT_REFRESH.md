# I18N POT Refresh

This project includes a pure-PHP helper to regenerate translation templates
without external tools. It scans the PHP source for gettext calls and writes
`artifacts/i18n/messages.pot` along with a small JSON summary.

## Usage

```bash
php scripts/pot-refresh.php
```

Running the script always exits with `0` and prints a one-line summary. The
artifacts directory will contain:

- `artifacts/i18n/messages.pot` – refreshed template
- `artifacts/i18n/pot-refresh.json` – counts and domain warnings

## Unit Test (opt-in)

A guarded unit test ensures the POT file stays in sync. Enable it with:

```bash
RUN_I18N_POT=1 composer test
```

The test skips if the file is missing or has fewer than 10 entries.

## QA Plan Mapping

POT refresh lives in Stage 4 (Persian/RTL) of the QA Plan and feeds the release
checks.

## Orchestrator / Finalizer

Both `scripts/qa-orchestrator.sh` and `scripts/release-finalizer.sh` call the
refresh script. They surface `pot_entries` and `domain_mismatch` counts and add
WARN lines to `GA_READY.txt` when mismatches are found or no entries exist.

## E2E RTL Snapshot

An optional smoke test captures RTL screenshots:

```bash
E2E=1 E2E_RTL=1 npx playwright test tests/e2e/rtl-snapshot.spec.ts
```

If `@axe-core/playwright` is installed, accessibility results are saved to
`artifacts/axe/`.
