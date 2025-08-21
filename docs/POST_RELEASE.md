# Post-Release Monitoring & Operations (SmartAlloc)

Run [`scripts/post-release-watch.sh`](../scripts/post-release-watch.sh) for an optional alerts checklist.

For release completion, see the runbooks in `docs/RELEASE_GATE.md` and execute `bash scripts/release-finalizer.sh`.

## Weekly
- Patchstack scan & dependency review (record findings)
- Review error logs & redaction samples (PII-free)

## Continuous Monitoring
- Error rate < 0.1% (Sentry)
- Web Vitals: LCP < 2.5s, CLS < 0.1 (Lighthouse/RUM)
- Export latency and failure rate dashboards

## Monthly
- Performance benchmark (form render/export)
- Review transient/options growth; retention jobs health

## Quarterly
- Compatibility matrix: PHP/WP/GF versions
- Security posture review (Semgrep/Snyk/Patchstack)

## Links
- Dashboards (fill in URLs)
- Playbooks & On-call (fill in)

## Alerts & SLOs

### Alerts
- Export error rate > 5 in 10m → notify Slack and email on-call.
- Circuit Breaker state=OPEN → immediate alert; page team.
- p95 response > 2s sustained for 3m → alert and create incident.

### Tooling
- APM: check New Relic and Sentry dashboards for spikes.
- Logging: ensure PII fields are masked; review structured log dashboards.

### Weekly Checks
- Run Patchstack security scan.
- Review DB health: indexes and table size trends.
- Verify error rate < 0.1%.
- Review LCP < 2.5s and CLS < 0.1 dashboards.
- Smoke test: onboard one new student end-to-end.

### Examples
```bash
# fetch latest QA report if available
[ -f qa-report.json ] && cat qa-report.json
[ -f qa-report.html ] && echo "see qa-report.html"
```
