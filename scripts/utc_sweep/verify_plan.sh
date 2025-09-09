#!/bin/bash
# UTC Sweep Verification Script

set -euo pipefail

# Run scanner
php scripts/utc_sweep/plan.php

# Verify JSON output exists and is valid
if [ ! -f "UTC_CANDIDATES.json" ]; then
    echo "ERROR: UTC_CANDIDATES.json not generated"
    exit 1
fi

# Validate JSON structure
if ! jq -e '.summary.total >= 0' UTC_CANDIDATES.json >/dev/null 2>&1; then
    echo "ERROR: Invalid JSON structure"
    exit 1
fi

# Cross-check with grep
GREP_COUNT=$(grep -r "current_time.*mysql" src/ includes/ app/ 2>/dev/null | wc -l || echo "0")
JSON_COUNT=$(jq -r '.summary.total' UTC_CANDIDATES.json)

echo "Scan complete: Found $JSON_COUNT instances (grep found $GREP_COUNT)"
exit 0
