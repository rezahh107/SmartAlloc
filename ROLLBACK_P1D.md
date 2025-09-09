# ROLLBACK — P1.D-TEST-004

**Scope:** Integration test removal

## Steps
1. Remove test file:
   ```bash
   rm -f tests/Integration/GF/AfterSubmissionSinglePathTest.php
   ```

2. Remove test artifact:
   ```bash
   rm -f INTEGRATION_TEST_ARTIFACT.json
   ```

3. Update state to clear lock:
   ```bash
   ./scripts/release-lock.sh tests/Integration/GF/AfterSubmissionSinglePathTest.php
   ```

4. Clean test cache/data:
   ```bash
   wp cache flush
   ```

## Verification
```bash
# Verify test file removed
test ! -f tests/Integration/GF/AfterSubmissionSinglePathTest.php && echo "\u2713 Test file removed"

# Verify artifact removed
test ! -f INTEGRATION_TEST_ARTIFACT.json && echo "\u2713 Test artifact removed"

# Verify no test references remain
grep -R "AfterSubmissionSinglePathTest" . --exclude-dir=.git | wc -l | grep -q '^0$' && echo "\u2713 No test references"
```
