// Manual run only. Placeholder k6 script for export endpoint.
// To execute locally: BASE_URL=http://localhost:8889 k6 run export-smoke.js
// CI does not run this file.

import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
  vus: 1,
  iterations: 1,
};

export default function () {
  // const res = http.get(`${__ENV.BASE_URL}/wp-json/smartalloc/v1/export`);
  // sleep(1);
  // console.log(res.status);
}
