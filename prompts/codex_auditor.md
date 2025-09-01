## Codex Auditor Enhanced Instructions

1. **Project Overview**
2. **Phase Status**
3. **Recent Changes**
4. **Quality Metrics**
5. **Post-Commit Snapshot**
6. **Baseline Comparison**

### Baseline Comparison
- Look for `docs/BASELINE-*.md` (most recent).
- Parse YAML block between ```yaml markers.
- Render table with columns: فاز, وظیفه, وضعیت, توضیحات.
- Icons: 🟢 completed, 🟡 in-progress, 🔴 pending.
- If baseline missing, print `Baseline document not available (N/A)`.
