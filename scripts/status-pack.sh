#!/usr/bin/env bash
set -euo pipefail

REPORT_FILE="reports/latest-status.md"
mkdir -p reports
printf "## Project Status\n" > "$REPORT_FILE"

BASELINE_FILE=$(find docs -name "BASELINE-*.md" -type f | sort -r | head -1 || true)
if [ -f "${BASELINE_FILE:-}" ]; then
  BASELINE_YAML=$(sed -n '/^```yaml$/,/^```$/p' "$BASELINE_FILE" | sed '1d;$d')
  echo "$BASELINE_YAML" > /tmp/baseline.yaml
  php -r "require 'vendor/autoload.php';\$p=new \\SmartAlloc\\Reports\\BaselineParser();\$d=\$p->parse(file_get_contents('/tmp/baseline.yaml'));if(\$d){echo json_encode(\$d);}" > /tmp/baseline.json || true
  if [ -s /tmp/baseline.json ]; then
    jq '.' /tmp/baseline.json > /tmp/baseline.json.tmp && mv /tmp/baseline.json.tmp /tmp/baseline.json
    php -r "require 'vendor/autoload.php';\$r=new \\SmartAlloc\\Reports\\BaselineComparisonRenderer();\$b=json_decode(file_get_contents('/tmp/baseline.json'), true);echo \$r->render(\$b);" >> "$REPORT_FILE"
  else
    echo '⚠️ سند مبنا یافت نشد یا قابل تجزیه نیست' >> "$REPORT_FILE"
  fi
else
  echo "No baseline document found" >&2
fi
