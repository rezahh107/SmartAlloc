const { execSync } = require('child_process');

function has(cmd) {
  try {
    execSync(cmd, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

if (!has('docker version')) {
  if (has('wp-env --version')) {
    console.error(`Docker not found. wp-env detected. Run:
npm run e2e:install && npm run e2e:all:wpenv`);
  } else {
    console.error(`Docker not found. Install Docker Desktop or wp-env. Then run:
npm run e2e:install && npm run e2e:up && npm run e2e:wait && npm run e2e:seed && npm run test:e2e`);
  }
  process.exit(1);
}

if (!has('docker compose version')) {
  console.error('Docker Compose not found. Install Docker Compose v2.');
  process.exit(1);
}

const url = process.env.WP_BASE_URL || 'http://localhost:8080';
if (!process.env.WP_BASE_URL) {
  console.log(`WP_BASE_URL not set. Defaulting to ${url}`);
} else {
  console.log(`WP_BASE_URL=${url}`);
}
