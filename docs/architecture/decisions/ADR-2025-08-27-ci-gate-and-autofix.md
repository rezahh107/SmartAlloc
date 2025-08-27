# ADR: Adopt 5D CI Gate + AUTO-FIX Loop
- Status: Accepted
- Date: 2025-08-27

## Context
CI builds were flaky and manual fixes slowed releases.

## Decision
Adopt a 5D CI gate enforcing security, logic, performance, readability, and goal checks, paired with an AUTO-FIX loop that generates fix prompts on failure.

## Consequences
- Ensures measurable quality before merge
- Developers receive actionable fix prompts
- Slightly longer pipeline runtime