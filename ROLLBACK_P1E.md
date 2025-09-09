# ROLLBACK — P1.E-INTEGRATE-005

**Scope:** Documentation and manifest removal

## Steps
1. Remove manifest file:
   ```bash
   rm -f GF_PATH_MANIFEST.json
   ```
2. Remove documentation:
   ```bash
   rm -f docs/GF_SINGLE_PATH_NOTES.md
   ```
3. Update state to clear locks:
   ```bash
   ./scripts/release-lock.sh docs/GF_SINGLE_PATH_NOTES.md
   ./scripts/release-lock.sh GF_PATH_MANIFEST.json
   ```

## Verification
```bash
# Verify files removed
test ! -f GF_PATH_MANIFEST.json && echo "✓ Manifest removed"
test ! -f docs/GF_SINGLE_PATH_NOTES.md && echo "✓ Documentation removed"

# Verify no references remain
grep -R "GF_PATH_MANIFEST\|GF_SINGLE_PATH_NOTES" . --exclude-dir=.git | wc -l | grep -q '^0$' && echo "✓ No references"
```
