# SmartAlloc Agent Guide

## Selective Quality Gates
- Run `composer run quality:selective` to lint and analyze only the staged PHP files.
- The script `scripts/selective-quality-gates.php` skips stub, mock, and fixture files and exits non-zero on failures.

## Baseline Requirements
- Verify baseline compliance with `php baseline-check --current-phase=FOUNDATION`.

## Patch Guard
- Patch Guard checks are currently disabled; no patch size limits are enforced.

## Pre-commit Flow
1. Stage changes using `git add`.
2. `composer run quality:selective`
3. `php baseline-check --current-phase=FOUNDATION`
4. Commit changes.
5. (Patch Guard step skipped)

All contributions must pass these checks.
