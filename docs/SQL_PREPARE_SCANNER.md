# SQL Prepare Scanner

This repository includes an opt‑in static scanner for detecting raw SQL
queries that bypass `$wpdb->prepare()`. It is conservative: false negatives are
tolerated but false positives are minimised.

## Heuristics

The scanner inspects PHP files under the given root and ignores `vendor/`,
`node_modules/`, `.git/`, `artifacts/`, and `coverage/`. A violation is
reported when:

* A call to `$wpdb->{query,get_results,get_row,get_var,get_col}` receives a
  string containing `SELECT`, `INSERT`, `UPDATE`, or `DELETE`.
* The argument is not proven to be the result of `$wpdb->prepare()`.
* One‑hop taint propagation is supported: assigning a raw SQL string to a
  variable and later passing it to `$wpdb` triggers a violation unless the
  variable originated from `$wpdb->prepare()`.
* Inline `$wpdb->prepare()` calls and variables assigned from
  `$wpdb->prepare()` are considered safe.

## Allowlist

Some legacy queries may be intentionally unprepared. These must be explicitly
allowlisted in `tools/sql-allowlist.json`:

```json
{
  "path/to/file.php": [
    {
      "fingerprint": "<sha1>",
      "reason": "legacy dynamic query reviewed on 2025-08-21"
    }
  ]
}
```

The `fingerprint` is `sha1(normalized(callsite_text))` where `normalized()`
trims leading/trailing whitespace and collapses internal whitespace to a single
space. The `callsite_text` is the SQL string or call expression that triggered
the violation.

To compute a fingerprint for a new allowlist entry:

```bash
echo -n "$(php -r 'echo preg_replace("/\\s+/", " ", trim("SELECT * FROM wp_posts"));')" | sha1sum
```

The allowlist is a last resort. Each entry must include a human justification
and review date. Regularly revisit allowlisted queries to ensure they remain
necessary.

## Output

Running `php scripts/scan-sql-prepare.php` writes
`artifacts/security/sql-prepare.json` with deterministic contents:

```json
{
  "generated_at_utc": "YYYY-MM-DDTHH:MM:SSZ",
  "total_files_scanned": 0,
  "violations": [
    {
      "file": "file.php",
      "line": 123,
      "call": "$wpdb->query",
      "sql_preview": "SELECT …",
      "fingerprint": "<sha1>",
      "allowlisted": false
    }
  ],
  "counts": {"violations": 0, "allowlisted": 0}
}
```

The script always exits with code `0` to remain advisory by default.
