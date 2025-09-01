## Codex Auditor Enhanced Instructions

When generating status reports, follow this EXACT structure:

1. **Standard Sections** (keep as-is):
   - Project Overview
   - Phase Status
   - Recent Changes
   - Quality Metrics
   - Post-Commit Snapshot

2. **NEW: Baseline Comparison Section** (add after Post-Commit Snapshot):
   ```markdown
   ## طبق «سند مبنا برای ادامه توسعه افزونه بر اساس وضعیت پروژه در تاریخ <date>» موارد زیر تکمیل و باقی مانده

   | فاز | وظیفه | وضعیت | توضیحات |
   |-----|-------|--------|----------|
   | foundation | security_framework | 🟢 completed | پیاده‌سازی چارچوب امنیتی |
   | expansion | notification_throttle | 🟡 in-progress | سیستم محدودسازی نرخ اعلانات |
   | expansion | rule_engine_composite | 🔴 pending | پشتیبانی از شرایط ترکیبی AND/OR |
   ```

3. **Implementation Details**:
   - Look for `docs/BASELINE-*.md` files (use most recent by filename)
   - Parse YAML block between ```yaml markers
   - Use status icons: 🟢 (completed), 🟡 (in-progress), 🔴 (pending)
   - If no baseline found, print: "Baseline document not available (N/A)"

4. **Phase Transition Logic** (preserve existing):
   - Foundation → Expansion: when Security ≥20 AND RuleEngine implemented
   - Expansion → Polish: when Security ≥22 AND Logic ≥18 AND Performance ≥18
   - Never regress phases

5. **File Updates Required**:
   - `prompts/codex_auditor.md`: Add baseline comparison instructions
   - `scripts/status-pack.sh`: Add baseline YAML extraction
   - Create initial `docs/BASELINE-2025-08-31.md` with current status
