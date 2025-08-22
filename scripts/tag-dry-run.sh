#!/usr/bin/env bash
set -e
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
summary="$root/artifacts/release/preflight.json"

version=$(php -r '@preg_match("/^\\s*Version:\\s*(.+)$/m", file_get_contents("'$root'/smart-alloc.php"), $m); echo $m[1] ?? "";')

echo "Simulated release steps:"
echo "1. Build distribution"
echo "2. Generate manifest and audits"
echo "3. Tag version v$version"
echo "4. Prepare release package"

echo ""
echo "Preflight summary:"
if [ -f "$summary" ]; then
  cat "$summary"
else
  echo "(not found)"
fi

exit 0
