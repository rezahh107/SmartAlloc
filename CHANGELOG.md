# Changelog

## [2025-08-27] CI Hardening & Dispatch Automation Complete

### Added
- 5D Scoring integrated with CI/CD (composer score:5d, update_state.sh)
- AUTO-FIX Mode with Codex prompt generation
- PHPCS integration for readability scoring
- Removal of direct superglobal usage (Form150 refactor)
- Codex prompt helpers (prepare_codex_prompt.sh, record_feedback.sh)
- PowerShell dispatch script (run_ci_dispatch.ps1) with GH CLI integration
- Documentation updates: Session Continuation Protocol, Codex handoff, CI Dispatch usage

### Changed
- CI workflow (.github/workflows/ci.yml) hardened with enforce gates and unique artifact names
- FEATURES.md and ai_context.json enriched with scores, red flags, feedback history

### Progress
- ✅ CI Hardening & Dispatch Automation complete
- ❌ Core Allocation Logic (next milestone)

## 1.0.0
- General availability: docs, runbooks, packaging workflow, compatibility matrix, and security guidelines.


## 1.0.0-rc.2
- Added Exporter/Importer and Reports & Logs test suites to release checklist.

## 1.0.0-rc.1
- Added release documentation and tooling.

## 0.9.0
- Allocator: initial release.
- GF hook: Gravity Forms integration.
- Exporter (Excel): config-driven exporter.
- Manual Review: admin review tools.
- Settings: configurable options.
- Health/Metrics: basic health and metrics endpoints.
- Hardening (DX): CLI doctor, webhook protections.
