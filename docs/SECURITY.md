# SmartAlloc Security Overview

## Threat Model

The plugin processes student and mentor data. Primary threats are unauthorized data access and tampering with allocations.

## Capability and Nonce Gates

All administrative actions require the `manage_smartalloc` capability. REST and form submissions include nonces validated with `wp_verify_nonce()` to prevent CSRF.

## Rate Limiting

The plugin relies on the hosting platform for HTTP rate limiting. Allocation and export commands are idempotent and can be retried safely.

## PII Masking

Logs and metrics mask email addresses, mobile numbers, and national IDs. Masked fields retain only the first two characters followed by `***`.

## Log Sinks

SmartAlloc writes to the default `error_log` and supports forwarding to syslog or external aggregators through the WordPress logging APIs. Operators must ensure transport is encrypted.
