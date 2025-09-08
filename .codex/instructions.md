# Codex Quick Reference

- Use `composer run quality:selective` to run selective lint and analysis on staged PHP files.
- Ensure baseline alignment with `php baseline-check --current-phase=FOUNDATION`.
- After committing, validate patch size with `./scripts/patch-guard-check.sh`.
