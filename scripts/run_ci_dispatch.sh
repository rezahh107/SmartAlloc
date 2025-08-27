#!/usr/bin/env bash
set -euo pipefail

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) is required" >&2
  exit 1
fi

if [ -z "${GH_TOKEN:-}" ]; then
  echo "GH_TOKEN is required" >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  printf '%s\n' "$GH_TOKEN" | gh auth login --with-token >/dev/null 2>&1
fi

run_ci() {
  local job=$1
  shift || true
  local info
  info=$(gh workflow run ci.yml -f job="$job" "$@" --json runNumber,url -q '.')
  local number url
  number=$(jq -r '.runNumber' <<<"$info")
  url=$(jq -r '.url' <<<"$info")
  echo "$job run: #$number $url"
}

run_ci qa
run_ci full -f inject_ci_failure=true
