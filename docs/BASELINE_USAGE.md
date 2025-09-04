# Baseline Verification Usage

## Direct Execution
```
./baseline-check --current-phase=foundation
./baseline-compare --help
./gap-analysis --target-phase=foundation
```

Baseline check reports now include `overall_score` and `phase_gate_status` fields for easier automation. `phase_gate_status`
returns `PASS` when the weighted overall score meets or exceeds the phase's `completion_target`, even if some individual
metrics are still below their required values.

## Composer Scripts
```
composer run baseline:foundation
composer run baseline:check -- --help
composer run baseline:gap-analysis -- --help
```

## Legacy Usage
```
php scripts/baseline-check.php --current-phase=foundation
php scripts/baseline-compare.php --help
php scripts/gap-analysis.php --target-phase=foundation
```

## Troubleshooting
- **Permission denied**: run `chmod +x baseline-check baseline-compare gap-analysis`
- **Script not found**: ensure you are in project root.
