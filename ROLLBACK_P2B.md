#!/bin/bash
# ROLLBACK SCRIPT - P2.B-CODEMOD-002

# Revert UTC codemod changes
git checkout -- src/Integration/ActionSchedulerAdapter.php \
               src/Infra/CLI/Commands.php \
               src/Infra/Repository/AllocationsRepository.php \
               src/Infra/Export/ExporterService.php

# Remove scripts and tests
rm -f scripts/utc_sweep/verify_codemod.sh
rm -f tests/utc/UtcCodemodUnitTest.php
rm -f UTC_REPORT.json
rm -f GLOBAL_STATE.json

echo "Rollback complete - P2.B-CODEMOD-002"
