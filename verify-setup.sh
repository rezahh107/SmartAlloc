#!/bin/bash
# verify-setup.sh
set -euo pipefail

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Verifying environment..."

# Check Docker
docker --version || { echo "Docker not found"; exit 1; }
docker compose version || { echo "Docker Compose not found"; exit 1; }

# Check Playwright
npx playwright --version || { echo "Playwright not found"; exit 1; }

# Check services
docker compose ps || { echo "Services not running"; exit 1; }

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] All checks passed."
