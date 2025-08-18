# SmartAlloc Webhooks

## Outgoing event
`smartalloc_allocation_committed` is sent after an allocation is finalized. The
payload contains only identifiers and status fields.

## Incoming webhook
`POST /smartalloc/v1/hook/allocate`

Headers:
- `Content-Type: application/json`
- `X-SmartAlloc-Timestamp: <unix epoch>`
- `X-SmartAlloc-Signature: hex(hmac_sha256(secret, raw_body))`

Enable this endpoint by setting **enable_incoming_webhook** and provide a
**webhook_secret** in the plugin settings. Example verification:

```php
$expected = hash_hmac('sha256', $body, $secret);
if (!hash_equals($expected, $signature)) {
    // reject
}
```

### cURL example
```
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-SmartAlloc-Timestamp: $(date +%s)" \
  -H "X-SmartAlloc-Signature: $sig" \
  -d '{"entry":1}' \
  https://example.com/wp-json/smartalloc/v1/hook/allocate
```
