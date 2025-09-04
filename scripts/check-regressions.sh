#!/usr/bin/env bash
set -euo pipefail

from=""
to=""
for arg in "$@"; do
  case "$arg" in
    --from=*) from="${arg#*=}" ;;
    --to=*) to="${arg#*=}" ;;
    *) echo "Usage: $0 --from=FILE --to=FILE" >&2; exit 2 ;;
  esac
done

if [[ -z "$from" || -z "$to" ]]; then
  echo "Usage: $0 --from=FILE --to=FILE" >&2
  exit 2
fi

parse_score(){
  local file=$1
  grep -E "^OVERALL SCORE" "$file" | awk -F': ' '{print $2}' | tr -d ' \t'
}

parse_status(){
  local file=$1
  grep -E "^PHASE GATE STATUS" "$file" | awk -F': ' '{print $2}' | tr -d ' \t'
}

score_from=$(parse_score "$from")
score_to=$(parse_score "$to")
status_from=$(parse_status "$from")
status_to=$(parse_status "$to")

if [[ -z "$score_from" || -z "$score_to" || -z "$status_from" || -z "$status_to" ]]; then
  echo "Could not parse reports" >&2
  exit 3
fi

regression=0
if (( $(echo "$score_to < $score_from" | bc -l) )); then
  echo "Regression detected: overall score decreased from $score_from to $score_to" >&2
  regression=1
fi

if [[ "$status_from" == "PASS" && "$status_to" == "FAIL" ]]; then
  echo "Phase gate regression: status changed from PASS to FAIL" >&2
  regression=1
fi

if (( regression == 1 )); then
  exit 1
fi

echo "No regressions detected"
