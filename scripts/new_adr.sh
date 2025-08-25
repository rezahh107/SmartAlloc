#!/usr/bin/env bash
set -euo pipefail
slug="${*:-new-decision}"
date="$(date -u +%Y%m%d)"
safe="$(echo "$slug" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g')"
file="docs/architecture/decisions/${date}_${safe}.md"
mkdir -p docs/architecture/decisions
cat > "$file" <<'MD'
# REPLACE ME

- **Status:** proposed
- **Date:** REPLACE-ME
- **Context:** …
- **Decision:** …
- **Consequences:** …
MD
sed -i.bak "s/REPLACE ME/${slug}/; s/REPLACE-ME/$(date -u +%Y-%m-%d)/" "$file" && rm -f "$file.bak"
echo "Created $file"
