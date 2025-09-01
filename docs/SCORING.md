# 5D Scoring (Security, Logic, Performance, Readability, Goal)

- Automated via `scripts/update_state.sh`
- Red Flags: direct superglobals, raw SQL without prepare
- Total: /125, plus Weighted %
- Outputs: `FEATURES.md` + `ai_context.json.current_scores`

## Feature Status Sync

`features.json` tracks the status of all features. At the start of CI, `scripts/sync-features-to-ai-context.php` copies these statuses into `ai_context.json` so scoring uses the latest data. The script tolerates an empty `features` array, and tests simulate this scenario to ensure synchronization remains accurate.

CI workflows explicitly run the sync script before executing `scripts/check_phase_transition.sh` to guarantee `ai_context.json` mirrors `features.json`. If `features.json` lacks entries, the phase transition check fails until synchronization populates the needed statuses. Additional gates such as `scripts/ci/ensure_ci_thresholds.sh` also run the sync step to guarantee `ai_context.json` reflects current features before score evaluation.
