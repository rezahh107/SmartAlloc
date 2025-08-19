const http = require('http');
const url = process.env.WP_BASE_URL || 'http://localhost:8080';
const deadline = Date.now() + 120000;

function ping() {
  http
    .get(url, (res) => {
      if (res.statusCode === 200) {
        process.exit(0);
      }
      retry();
    })
    .on('error', (err) => {
      if (['ENOTFOUND', 'ECONNREFUSED'].includes(err.code)) {
        console.error(`Cannot reach ${url}: ${err.code}. Is the stack running?`);
        process.exit(1);
      }
      retry();
    });
}

function retry() {
  if (Date.now() < deadline) {
    setTimeout(ping, 1500);
  } else {
    console.error(`WordPress unreachable at ${url}. Did you run: npm run e2e:up ?`);
    process.exit(1);
  }
}

ping();
