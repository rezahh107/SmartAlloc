#!/usr/bin/env bash
set -euo pipefail

JSON_FILE="ai_context.json"
PHASES_FILE="ai_config/project_phases.yml"
TEMPLATE_FILE="ai_outputs/phase_transition_template.md"
OUTPUT_FILE="phase_report.md"

# Validate JSON structure
jq empty "$JSON_FILE"

ruby - "$JSON_FILE" "$PHASES_FILE" "$TEMPLATE_FILE" "$OUTPUT_FILE" <<'RUBY'
require 'json'
require 'yaml'

json_file, phases_file, template_file, output_file = ARGV
context = JSON.parse(File.read(json_file))
phases = YAML.load_file(phases_file)['phases']
template = File.read(template_file)

scores = context['current_scores'] || {}
features = (context['features'] || []).reduce({}) { |h,f| h.merge(f) }

current_phase = nil
next_phase = nil

phases.each do |name, data|
  req = data['requirements'] || {}
  min_scores = req['min_scores'] || {}
  feat_req = req['features_required'] || []
  ok_scores = min_scores.all? { |k,v| (scores[k] || 0) >= v }
  ok_features = feat_req.all? { |f| k,v = f.first; features[k] == v }
  unless ok_scores && ok_features
    current_phase = name
    next_phase = data['next_phase']
    break
  end
end
current_phase ||= phases.keys.last
next_phase ||= nil

req = phases[current_phase]['requirements']
min_scores = req['min_scores'] || {}
feat_req = req['features_required'] || []

score_lines = %w[security logic performance readability goal].map do |dim|
  cur = scores[dim] || 0
  reqd = min_scores[dim] || 0
  check = cur >= reqd ? '✅' : '❌'
  "| #{dim.capitalize} | #{cur} | #{reqd} | #{check} |"
end

feature_lines = if feat_req.empty?
  ["| No feature requirements | | |"]
else
  feat_req.map do |f|
    k,v = f.first
    cur = features[k] || 'missing'
    check = cur == v ? '✅' : '❌'
    "| #{k} | #{cur} | #{v} | #{check} |"
  end
end
feature_table = ["| Feature | Current | Required | Status |", "|---------|---------|----------|--------|", *feature_lines].join("\n")

blockers = []
min_scores.each { |k,v| blockers << "#{k} below requirement" if (scores[k] || 0) < v }
feat_req.each { |f| k,v = f.first; blockers << "#{k} not #{v}" unless features[k] == v }
blocker_list = blockers.empty? ? "None" : blockers.map { |b| "- #{b}" }.join("\n")
status = blockers.empty? ? 'READY' : 'BLOCKED'

actions = context['next_actions'] || []
action1 = actions[0] || 'TBD'
action2 = actions[1] || 'TBD'

report = template
report = report.gsub('{{DATE}}', context['last_update_utc'])
report = report.gsub('{{CURRENT_PHASE}}', current_phase)
report = report.gsub('{{NEXT_PHASE}}', next_phase.to_s)
report = report.gsub('{{READY|BLOCKED}}', status)
report = report.sub('| Security | {{X}} | {{Y}} | {{✅/❌}} |', score_lines[0])
report = report.sub('| Logic | {{X}} | {{Y}} | {{✅/❌}} |', score_lines[1])
report = report.sub('| Performance | {{X}} | {{Y}} | {{✅/❌}} |', score_lines[2])
report = report.sub('| Readability | {{X}} | {{Y}} | {{✅/❌}} |', score_lines[3])
report = report.sub('| Goal | {{X}} | {{Y}} | {{✅/❌}} |', score_lines[4])
report = report.gsub('{{FEATURE_CHECK_TABLE}}', feature_table)
report = report.gsub('{{LIST_OF_BLOCKERS}}', blocker_list)
report = report.gsub('{{ACTION_1}}', action1)
report = report.gsub('{{ACTION_2}}', action2)

File.write(output_file, report)
RUBY

echo "Report written to $OUTPUT_FILE"
