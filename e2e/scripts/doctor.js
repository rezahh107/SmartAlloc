const { execSync } = require('child_process');

function has(cmd) {
  try {
    execSync(cmd, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

if (!has('npx @wp-playground/cli --version')) {
  console.error('Playground CLI unavailable or offline. For Docker or wp-env paths run:\n npm run e2e:all:docker\n npm run e2e:all:wpenv');
}

if (!has('docker version')) {
  if (has('wp-env --version')) {
    console.error('wp-env still needs Docker; use Playground path instead.');
  }

  const nodeMajor = parseInt(process.versions.node.split('.')[0], 10);
  if (nodeMajor < 20) {
    console.error('Node 20+ required for Playground CLI. Please upgrade Node.');
  }

  if (process.env.E2E_AUTO_INSTALL === '1' && !has('npx start-server-and-test --version')) {
    try {
      execSync('npm i -D start-server-and-test', { stdio: 'inherit' });
    } catch (err) {
      console.error('Failed to install start-server-and-test:', err.message);
    }
  }

  console.error('Docker not found. Run:\n npm run e2e:all');
  process.exit(1);
}

if (!has('docker compose version')) {
  console.error('Docker Compose not found. Install Docker Compose v2.');
  process.exit(1);
}

const url = process.env.WP_BASE_URL || 'http://localhost:9400';
if (!process.env.WP_BASE_URL) {
  console.log(`WP_BASE_URL not set. Defaulting to ${url}`);
} else {
  console.log(`WP_BASE_URL=${url}`);
}

