# SmartAlloc LLM Optimized v2.9.3 — E2E Automation

This canonical document outlines the end-to-end (E2E) automation process for SmartAlloc's LLM integrations. It summarizes the pipeline stages, gating strategy, and CI requirements used across the project.

The following appendices expand on additional policies and scoring systems.

---
## Appendix D — 5D Scoring Integration (Selection Policy & CI)

> این ضمیمه، «سیستم امتیازدهی ۵بعدی» موجود در پروژه را به‌عنوان **معیار کلیدی انتخاب** در کنار Gateها وارد جریان E2E می‌کند.

### D.1 تعریف 5D و Weighted Percent
- ابعاد پایه (هرکدام 25): **Security**, **Logic**, **Performance**, **Readability**, **Goal Achievement** — مجموع ۱۲۵
- **Weighted Percent (پیشنهادی):**
  
  *(Sec×2 + Log×2 + Perf×1 + Read×1 + Goal×2) / 200 × 100*

- منبع محاسبه: `scripts/update_state.sh`  
  - ورودی: خروجی linters/tests/perf/analysis
  - خروجی: `FEATURES.md` (نمایش) و `ai_context.json` (مصرف CI)

### D.2 سیاست انتخاب (Selector Policy)
**مرحله A — Hard Gates (حذف فوری):**
۱) هر Gate اصلی = FAIL (Security/Testing/WP-Standards/Performance SLA)
۲) نقض UTC Everywhere
3) Patch-Guard cap شکسته (فایل/LOC)
4) Site Health قرمز

**مرحله B — امتیازدهی 5D:**
- معیار اصلی: `ai_context.json.analysis.weighted_percent` (بیشتر = بهتر)

**مرحله C — Tie-breakers:**
1) LOC تغییر کمتر → ۲) فایل تغییر کمتر → ۳) بدون DB migration → 4) p95 بهتر

> فقط برنده وارد Auto-PR (و در صورت فعال بودن، Auto-Merge) می‌شود؛ سایر واریانت‌ها آرشیو می‌شوند.

### D.3 جریان CI برای MRG (Matrix + 5D)
**File:** `.github/workflows/mrg-evaluate.yml`
```yaml
name: Evaluate Variants with 5D Scoring (E2E)

on:
  repository_dispatch:
    types: [codex-mrg]

permissions:
  contents: write
  pull-requests: write

jobs:
  eval:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        variant: ${{ fromJson(github.event.client_payload.manifest).variants }}

    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none

      - name: Apply branch/patch
        run: |
          if [ -n "${{ matrix.variant.build.branch }}" ]; then
            git fetch origin "${{ matrix.variant.build.branch }}"
            git checkout "${{ matrix.variant.build.branch }}"
          fi

      - name: Build/Lint/Test
        run: |
          set -e
          ${ matrix.variant.build.cmd }
          ${ matrix.variant.lint.cmd }
          ${ matrix.variant.test.cmd }

      - name: Perf & Patch-Guard
        run: |
          set -e
          ${ matrix.variant.perf.cmd } || true
          php scripts/patch-guard.php --cap-files=${ matrix.variant.patch_guard.max_files } --cap-loc=${ matrix.variant.patch_guard.max_loc }

      - name: 5D Score (update_state.sh)
        run: |
          bash scripts/update_state.sh
          test -f ai_context.json || (echo "ai_context.json missing" && exit 1)

      - name: Export Selector Input
        id: export
        run: |
          mkdir -p build/selector
          jq -n --arg id "${{ matrix.variant.variant_id }}" \
                --slurpfile ctx ai_context.json \
                '{variant_id:$id, ai_context:$ctx[0]}' \
            > "build/selector/${{ matrix.variant.variant_id }}.json"

      - name: Upload selector artifact
        uses: actions/upload-artifact@v4
        with:
          name: selector-${{ matrix.variant.variant_id }}
          path: build/selector/${{ matrix.variant.variant_id }}.json

  select:
    runs-on: ubuntu-latest
    needs: [eval]
    steps:
      - uses: actions/download-artifact@v4
        with: { path: build/selector }
      - name: Pick winner by gates + weighted_percent
        id: pick
        run: |
          php scripts/selector.php --input build/selector --out build/WINNER.json
          cat build/WINNER.json
      - name: Auto-PR (winner only)
        env: { GH_TOKEN: ${{ secrets.GITHUB_TOKEN }} }
        run: |
          BR=$(jq -r '.winner.branch // "release/mrg-" + env.GITHUB_RUN_ID' build/WINNER.json)
          T=$(jq -r '.winner.title  // "Auto-PR (MRG Winner)"' build/WINNER.json)
          B=$(jq -r '.winner.body   // "Selected via gates + 5D scoring."' build/WINNER.json)
          git checkout -b "$BR" || true
          echo "winner: $(date -u +%FT%TZ)" > .mrg-winner-stamp.md
          git add .mrg-winner-stamp.md
          git -c user.name="ci-bot" -c user.email="ci@users.noreply.github.com" commit -m "chore: mrg winner stamp" || true
          git push origin "$BR" || true
          gh pr create --base "${{ github.event.client_payload.base || 'main' }}" --head "$BR" --title "$T" --body "$B"
      - name: Auto-merge (optional)
        if: ${{ github.event.client_payload.auto_merge || true }}
        env: { GH_TOKEN: ${{ secrets.GITHUB_TOKEN }} }
        run: |
          NUM=$(gh pr view --json number --jq .number)
          gh pr merge "$NUM" --merge --delete-branch --admin || true
      - name: Notify context_pool webhook
        if: ${{ secrets.SMARTALLOC_WEBHOOK_URL != '' && secrets.SMARTALLOC_WEBHOOK_TOKEN != '' }}
        run: |
          PR=$(gh pr view --json number --jq .number)
          URL=$(gh pr view --json url --jq .url)
          curl -sS -X POST "$SMARTALLOC_WEBHOOK_URL" \
            -H "Authorization: Bearer $SMARTALLOC_WEBHOOK_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"event\":\"codex-mrg\",\"pr_number\":$PR,\"pr_url\":\"$URL\"}"
        env:
          SMARTALLOC_WEBHOOK_URL: ${{ secrets.SMARTALLOC_WEBHOOK_URL }}
          SMARTALLOC_WEBHOOK_TOKEN: ${{ secrets.SMARTALLOC_WEBHOOK_TOKEN }}
```

### D.4 `scripts/selector.php` (نمونهٔ مرجع)

```php
<?php
/**
 * Selector: حذف واریانت‌های Hard-Fail و انتخاب بالاترین weighted_percent.
 * Usage: php scripts/selector.php --input build/selector --out build/WINNER.json
 */
$in  = null; $out = null;
for ($i=1; $i<count($argv); $i++) {
  if ($argv[$i] === '--input') $in = $argv[++$i];
  if ($argv[$i] === '--out')   $out = $argv[++$i];
}
if (!$in || !$out) { fwrite(STDERR, "Usage: --input <dir> --out <file>\n"); exit(2); }

$files = glob(rtrim($in,'/').'/*.json'); if (!$files) { exit(3); }

$candidates = [];
foreach ($files as $f) {
  $j = json_decode(file_get_contents($f), true);
  if (!$j || empty($j['ai_context'])) continue;
  $ctx = $j['ai_context'];
  $score = $ctx['analysis']['weighted_percent'] ?? 0;

  // Hard gates
  $g = $ctx['gates'] ?? [];
  $hardFail = false;
  foreach (['security','testing','wp_standards','performance'] as $k) {
    if (isset($g[$k]) && strtolower($g[$k]) === 'fail') { $hardFail = true; break; }
  }
  if (isset($ctx['utc_everywhere']) && $ctx['utc_everywhere'] === false) $hardFail = true;
  if (!empty($ctx['patch_guard']['exceeded'])) $hardFail = true;
  if (!empty($ctx['site_health']) && strtolower($ctx['site_health']) === 'red') $hardFail = true;

  if ($hardFail) continue;

  $candidates[] = [
    'variant_id' => $j['variant_id'] ?? basename($f, '.json'),
    'score'      => $score,
    'loc'        => $ctx['diff']['loc']   ?? 999999,
    'files'      => $ctx['diff']['files'] ?? 999999,
    'branch'     => $ctx['git']['branch'] ?? null,
    'title'      => $ctx['pr']['title']   ?? null,
    'body'       => $ctx['pr']['body']    ?? null,
    'raw'        => $j,
  ];
}

usort($candidates, function($a, $b) {
  if ($a['score'] !== $b['score']) return ($a['score'] > $b['score']) ? -1 : 1;
  if ($a['loc']   !== $b['loc'])   return ($a['loc']   < $b['loc'])   ? -1 : 1;
  if ($a['files'] !== $b['files']) return ($a['files'] < $b['files']) ? -1 : 1;
  return 0;
});

if (empty($candidates)) {
  file_put_contents($out, json_encode(['winner' => null, 'reason' => 'no passing variants'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  exit(1);
}

$winner = $candidates[0];
file_put_contents($out, json_encode(['winner' => $winner], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
```
