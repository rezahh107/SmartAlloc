# Security Scanners

The `scripts/scan-secrets.php` tool scans the repository for
credentials or other sensitive tokens. It searches for common patterns
such as:

- AWS access keys (`AKIA…`)
- Slack webhooks
- JSON/YAML key/value pairs
- dotenv style `VAR=VALUE` entries
- JWTs and WordPress salts

In addition to pattern matching, the scanner performs a Shannon entropy
check on any long token. The default entropy threshold is **4.5** and
can be overridden via `--entropy-threshold=<float>`.

Allowlisting
------------
Findings may be allowlisted using `.qa-allowlist.json` at the repository
root:

```json
{
  "secrets": [
    { "pattern": "AKIAALLOWLISTED…", "reason": "fixture" },
    { "pattern": "*.pem", "reason": "test keys" }
  ]
}
```

`pattern` values are matched against the filename (glob), the snippet
content (regular expression) or the exact `snippet_hash` reported by the
scanner.

Running Locally
---------------

```sh
php scripts/scan-secrets.php
php scripts/scan-secrets.php --entropy-threshold=5.0
```

Results are written to `artifacts/security/secrets.json` and the script
always exits with code `0` (advisory).

