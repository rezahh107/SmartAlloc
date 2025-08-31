#!/bin/bash
set -euo pipefail

CONTEXT_FILE="${1:-ai_context.json}"
PHASE_CONFIG="ai_config/project_phases.yml"

# Validate inputs
[[ -f "$CONTEXT_FILE" ]] || { echo "Error: $CONTEXT_FILE not found"; exit 1; }
[[ -f "$PHASE_CONFIG" ]] || { echo "Error: $PHASE_CONFIG not found"; exit 1; }

# Parse current phase and scores
CURRENT_PHASE=$(jq -r '.current_phase // "foundation"' "$CONTEXT_FILE")
SCORES=$(jq -r '.scores // {}' "$CONTEXT_FILE")
# Collect implemented features from context as object of statuses
FEATURES_JSON=$(jq '.features // {} | if type=="array" then map({(.):"present"}) | add else . end' "$CONTEXT_FILE")

# Get next phase requirements using yq
NEXT_PHASE=$(yq eval ".phases.$CURRENT_PHASE.next_phase" "$PHASE_CONFIG")
[[ "$NEXT_PHASE" == "null" ]] && { echo "Already at final phase"; exit 0; }

# Check score requirements
REQUIRED_SCORES=$(yq eval ".phases.$NEXT_PHASE.requirements.min_scores" "$PHASE_CONFIG" -o json)
# Required features for next phase as JSON array of objects
REQUIRED_FEATURES_JSON=$(yq eval -o=json ".phases.$NEXT_PHASE.requirements.features_required" "$PHASE_CONFIG")

READY=true
while IFS= read -r dimension; do
    REQUIRED=$(echo "$REQUIRED_SCORES" | jq -r ".$dimension")
    CURRENT=$(echo "$SCORES" | jq -r ".$dimension // 0")

    if [[ $CURRENT -lt $REQUIRED ]]; then
        echo "❌ $dimension: $CURRENT < $REQUIRED (required)"
        READY=false
    else
        echo "✅ $dimension: $CURRENT >= $REQUIRED"
    fi
done < <(echo "$REQUIRED_SCORES" | jq -r 'keys[]')

# Check feature requirements
while read -r req; do
    feature=$(echo "$req" | jq -r 'keys[0]')
    required_status=$(echo "$req" | jq -r '.[]')
    current_status=$(echo "$FEATURES_JSON" | jq -r --arg f "$feature" '.[$f] // ""')

    if [[ "$current_status" == "$required_status" ]]; then
        echo "✅ feature: $feature $current_status"
    else
        echo "❌ feature: $feature requires $required_status (current: ${current_status:-missing})"
        READY=false
    fi
done < <(echo "$REQUIRED_FEATURES_JSON" | jq -c '.[]')

if $READY; then
    echo "✅ Ready to transition from $CURRENT_PHASE to $NEXT_PHASE"
    exit 0
else
    echo "❌ Not ready for phase transition"
    exit 1
fi
