#!/bin/bash
# chrome-setup.sh
set -euo pipefail

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Installing Chromium dependencies..."

# Install Playwright browsers
npx playwright install chromium
npx playwright install-deps chromium

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Chromium setup complete."
