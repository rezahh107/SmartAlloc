#!/usr/bin/env bash
set -euo pipefail

job="${1:-qa}"

if [ -z "${GH_TOKEN:-}" ]; then
  echo "GH_TOKEN is required" >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  printf '%s\n' "$GH_TOKEN" | gh auth login --with-token >/dev/null 2>&1
fi

run_number=$(gh workflow run ci.yml -f job="$job" --repo rezahh107/SmartAlloc --json runNumber -q '.runNumber')

echo "Triggered run #${run_number}"
