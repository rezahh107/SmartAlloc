# Ops / SRE Triage

Quick reference for post-deploy verification and common production issues. See [POST_RELEASE.md](POST_RELEASE.md) for full procedures.

## Verify Manifest & Signatures

1. Navigate to `artifacts/dist/`.
2. Ensure `manifest.json` exists.
3. Verify checksum:
   ```sh
   openssl dgst -sha256 manifest.json
   cat manifest.sha256
   ```
4. Verify signature when keys are available:
   ```sh
   openssl dgst -sha256 -verify pubkey.pem -signature manifest.sig manifest.json
   gpg --verify manifest.asc manifest.json
   ```

## Health Endpoints

- `GET /wp-json/smartalloc/v1/health` – returns `{ok, db, cache, version}`.
- `GET /wp-json/smartalloc/v1/exports` – basic auth required; confirms exporter alive.

## Breaker States

Check option flags to see if circuits are open:

```sh
wp option get smartalloc_breaker_exports
wp option get smartalloc_breaker_webhooks
```

`0` means closed/healthy, `1` means open (traffic stopped).

## Common Runbooks

### Exports Failing
- Check queue: `wp smartalloc queue status`
- Review logs for `ExportService` errors.
- Retry with `wp smartalloc export --retry`.

### Redis Down
- Health endpoint `cache` will be false.
- Restart service or failover to backup.
- Clear object cache when restored.

### Database Slow
- Health endpoint `db` false or high latency.
- Inspect `SHOW PROCESSLIST` and slow query logs.
- Consider read replica or enabling query cache.

---
For more post-release tasks, see [POST_RELEASE.md](POST_RELEASE.md).
