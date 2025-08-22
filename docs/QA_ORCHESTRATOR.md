# QA Orchestrator

`scripts/qa-orchestrator.sh` runs advisory quality checks and gathers their
artifacts. Each step is optional; missing tools are skipped and the script
always exits with zero.

After invoking available scanners it builds an index at
`artifacts/qa/index.html`. The index is rendered with `dir="rtl"` and links
to the JSON or HTML reports for:

- coverage/coverage.json
- schema/schema-validate.json
- security/sql-prepare.json
- security/rest-permissions.json
- security/secrets.json
- compliance/license-audit.json
- security/headers.json
- qa-report.html / qa-report.json

The index uses relative paths so the entire `artifacts` directory can be
shared as-is.
