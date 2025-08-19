# Load Test Scripts

Sample [k6](https://k6.io) scenario to stress SmartAlloc export endpoint.

```bash
k6 run export-load.js --env BASE_URL=https://site.example
```

The script issues 50 concurrent export requests for 30s and asserts that each
request either succeeds or is rate limited (HTTP 429). No export files should be
duplicated when locks are effective.
