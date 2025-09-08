# SmartAlloc Agent Guide

## Selective Quality Gates
- Run `composer run quality:selective` to lint and analyze only the staged PHP files.
- The script `scripts/selective-quality-gates.php` skips stub, mock, and fixture files and exits non-zero on failures.

## Baseline Requirements
- Verify baseline compliance with `php baseline-check --current-phase=FOUNDATION`.

## Patch Guard
- After committing, run `./scripts/patch-guard-check.sh` to ensure patch size stays within branch limits.
- Default cap: 10 files and 300 lines; branch prefixes adjust these caps (see script for details).

## Pre-commit Flow
1. Stage changes using `git add`.
2. `composer run quality:selective`
3. `php baseline-check --current-phase=FOUNDATION`
4. Commit changes.
5. `./scripts/patch-guard-check.sh`

All contributions must pass these checks.
