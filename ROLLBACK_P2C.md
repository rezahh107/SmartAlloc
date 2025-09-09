#!/bin/bash
# Rollback script for P2.C-GUARD-003
rm -f src/Admin/SiteHealth/UtcHealthGuard.php
rm -f src/Runtime/UtcRuntime.php
rm -f scripts/utc_sweep/verify_health.sh
rm -f tests/utc/UtcHealthIntegrationTest.php
rm -f docs/UTC_INVARIANT.md
rm -f UTC_HEALTH_SPEC.json
# restore plugin file
if git checkout -- smart-alloc.php; then echo "Restored smart-alloc.php"; fi
# update global state
if [ -f GLOBAL_STATE.json ]; then
  jq '.status="rolled_back"|.locks.release=.locks.acquire|.locks.acquire=[]' GLOBAL_STATE.json > GLOBAL_STATE.tmp && mv GLOBAL_STATE.tmp GLOBAL_STATE.json
fi
echo "Rollback complete"
