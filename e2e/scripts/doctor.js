const { execSync } = require('child_process');

function has(cmd) {
  try {
    execSync(cmd, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

function online() {
  try {
    execSync('curl -I https://wordpress.org', { stdio: 'ignore' });
    return true;
  } catch (err) {
    const msg = (err.stderr || err.message || '').toString();
    if (msg.includes('ENETUNREACH')) {
      console.error('Network unreachable. Playground needs internet.\nFor Docker or wp-env fallback run:\n npm run e2e:all:docker\n npm run e2e:all:wpenv');
      return false;
    }
    return true;
  }
}

if (!online()) {
  // E2E is optional; succeed with hint when offline.
  process.exit(0);
}

if (!has('wp-playground-cli --version')) {
  console.error('Playground CLI not found. For Docker or wp-env paths run:\n npm run e2e:all:docker\n npm run e2e:all:wpenv');
  process.exit(1);
}

const url = process.env.WP_BASE_URL || 'http://localhost:9400';
console.log(`WP_BASE_URL=${url}`);
