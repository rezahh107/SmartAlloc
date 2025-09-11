# CI Quality Gates

## Overview
All code must pass these quality gates before merge.

## Gates

### 1. Security Gate
- **Tool**: Composer audit
- **Pass Criteria**: No Critical or High vulnerabilities
- **Command**: `composer audit`

### 2. Performance Gate
- **Metric**: p95 response time
- **Pass Criteria**: < 500ms
- **Command**: `php scripts/perf-microbench.php`

### 3. Testing Gate
- **Coverage**: Unit + E2E tests
- **Pass Criteria**: All tests pass
- **Commands**: 
  - `composer test:unit`
  - `npm run test:e2e`

### 4. WP Standards Gate
- **Tool**: PHPCS with WordPress standard
- **Pass Criteria**: No errors
- **Command**: `composer phpcs`

### 5. Patch-Guard Gate
- **Limits**: ≤10 files, ≤300 LOC (excluding CI/Docs)
- **Check**: Automated in CI

### 6. Site Health Gate
- **Checks**: DB connection, migrations, WP health
- **Pass Criteria**: Status = "good"

## Automation

All gates run automatically on:
- Pull requests
- Pushes to main/develop
- Manual workflow dispatch

Failed gates create HANDOFF_PACKET.json for remediation.
