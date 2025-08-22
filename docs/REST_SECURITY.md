# REST Security

`scan-rest-permissions.php` statically analyses PHP files for `register_rest_route()`
usage. It extracts namespace, route, methods, callbacks and warns when
permission callbacks are missing or overly permissive.

## Allowlist

`qa/allowlist/rest-permissions.yml` lists read-only routes allowed to use
`__return_true`:

```yaml
# namespace/route
- smartalloc/v1/public
```

## Heuristics

* Mutating methods (POST/PUT/PATCH/DELETE) must check capabilities using
  `current_user_can( SMARTALLOC_CAP )` (or stronger) and validate a nonce or
  signature.
* Read-only routes with `__return_true` must be allowlisted.

## Running locally

```bash
php scripts/scan-rest-permissions.php
```

The script writes `artifacts/security/rest-permissions.json` and always exits 0.
