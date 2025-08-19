import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 50,
  duration: '30s',
};

const BASE = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
  const payload = JSON.stringify({ from: '2024-01-01', to: '2024-01-02' });
  const res = http.post(`${BASE}/wp-json/smartalloc/v1/export`, payload, {
    headers: { 'Content-Type': 'application/json' },
  });
  check(res, {
    'rate limit or success': (r) => r.status === 200 || r.status === 429,
  });
  sleep(1);
}
