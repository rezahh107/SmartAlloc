import http from 'k6/http';
import { sleep } from 'k6';

export let options = { vus: 1, duration: '10s' };

export default function () {
  // NOTE: manual usage only; do NOT run in CI.
  // const url = __ENV.EXPORT_URL || 'http://localhost:8889/wp-json/smartalloc/v1/exports/recent';
  // const res = http.get(url);
  // Check status etc. if you wire it locally.
  sleep(1);
}
