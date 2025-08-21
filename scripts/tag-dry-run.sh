#!/usr/bin/env bash
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

plugin_version=$(php -r '@preg_match("/^\\s*Version:\\s*(.+)$/m", file_get_contents($argv[1] ?? ""), $m); echo $m[1] ?? "";' "$root/smart-alloc.php" 2>/dev/null)
readme_tag=$(php -r '@preg_match("/^Stable tag:\\s*(.+)$/m", file_get_contents($argv[1] ?? ""), $m); echo $m[1] ?? "";' "$root/readme.txt" 2>/dev/null)

tag="$plugin_version"
if [ -n "$readme_tag" ] && [ "$plugin_version" != "$readme_tag" ]; then
  tag="$plugin_version (plugin) / $readme_tag (readme)"
fi

date=$(date +%F)

echo "Tag: $tag"
echo "Date: $date"
echo "Artifacts:"
echo " - $root/artifacts/dist/manifest.json"
echo " - $root/artifacts/dist/sbom.json"
echo " - $root/artifacts/dist/release-notes.md"

exit 0
