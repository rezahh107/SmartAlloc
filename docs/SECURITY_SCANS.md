# Security Scans

This project can be scanned locally for known vulnerabilities. These checks are optional and are not wired into CI by default.

## WPScan

Run [WPScan](https://wpscan.com) against a local WordPress site to look for core, plugin, and theme issues:

```bash
wpscan --url http://localhost --api-token YOUR_TOKEN
```

## Patchstack, Semgrep, and Snyk

These tools can run in a separate, non-blocking job to provide additional coverage:

```bash
composer sec:wp      # Patchstack scan (requires access to repo)
composer sec:semgrep # Semgrep static analysis
snyk test --file=composer.lock
```

Configure your CI to treat these scans as informative only. Failures should not block merges unless you decide to enforce them.
