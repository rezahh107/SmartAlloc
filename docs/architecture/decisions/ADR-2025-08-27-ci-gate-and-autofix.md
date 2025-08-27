# ADR: Adopt 5D CI Gate + AUTO-FIX Loop
- Status: Accepted
- Date: 2025-08-27

## Context
CI builds were flaky and manual fixes slowed releases.

## Decision
Enforce a five-dimensional quality gate (Security, Logic, Performance, Readability, Goal) in CI. Failed checks trigger an AUTO-FIX loop.

## Consequences
- Higher baseline quality before merge
- Longer build times from extra verification
- Developers must review auto-generated patches
