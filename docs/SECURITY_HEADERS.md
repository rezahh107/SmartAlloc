# Security Headers

The `scripts/headers-guard.php` utility checks HTTP responses for common
security headers. The following headers are expected:

- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy

If a header is intentionally omitted in certain environments, list it in
the allowlist below. The guard will ignore missing headers that appear
here.

## Allowlist

(No allowlisted headers.)
