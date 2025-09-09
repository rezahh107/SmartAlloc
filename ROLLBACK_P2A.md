#!/bin/bash
# ROLLBACK SCRIPT - P2.A-PLAN-001

# Remove all created files
rm -f scripts/utc_sweep/plan.php
rm -f scripts/utc_sweep/verify_plan.sh
rm -rf tests/utc/
rm -rf tests/fixtures/utc/
rm -f UTC_CANDIDATES.json
rm -f DRY_RUN_DIFF.txt

# Update GLOBAL_STATE.json
jq '.status = "rolled_back" | .locks.release = .locks.acquire | .locks.acquire = []' GLOBAL_STATE.json > tmp.json && mv tmp.json GLOBAL_STATE.json

echo "Rollback complete - P2.A-PLAN-001"
