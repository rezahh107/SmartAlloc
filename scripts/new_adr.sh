#!/usr/bin/env bash
set -euo pipefail
slug="${*:-new-decision}"
date="$(date -u +%Y%m%d)"
safe="$(echo "$slug" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g')"
dir="docs/architecture/decisions"
file="${dir}/${date}_${safe}.md"
tmpl="${dir}/_template.md"

mkdir -p "$dir"

if [[ -f "$tmpl" ]]; then
  title="${slug}"
  today="$(date -u +%Y-%m-%d)"
  sed "s/{TITLE}/${title}/g; s/{YYYY-MM-DD}/${today}/g" "$tmpl" > "$file"
else
  cat > "$file" <<'MD'
# REPLACE ME

- **Status:** proposed
- **Date:** REPLACE-ME
- **Context:** …
- **Decision:** …
- **Consequences:** …
MD
  sed -i.bak "s/REPLACE ME/${slug}/; s/REPLACE-ME/$(date -u +%Y-%m-%d)/" "$file" && rm -f "$file.bak"
fi

echo "Created $file"
