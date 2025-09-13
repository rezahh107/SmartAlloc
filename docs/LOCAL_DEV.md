SmartAlloc Local Development Guide (Docker-First)

Prerequisites
- Docker Desktop + Docker Compose enabled
- Git

Key Commands
- Pre-commit (runs quality gates + baseline + patch guard):
  - git commit
  - Manual: ./scripts/precommit-docker.sh
- Pre-push (runs tests + patch guard):
  - git push (tests warn by default; patch guard blocks)
  - Manual: ./scripts/prepush-docker.sh
  - Strict mode (tests block): PREPUSH_STRICT=1 git push

Branch-based Strictness
- pre-push tests are blocking by default on: main, master, develop, release/*, hotfix/*
- Override any time: PREPUSH_STRICT=0 (disable) or 1 (enable)

How It Works
- Everything runs inside the wordpress Docker container
- If containers were not running, scripts start them and auto-stop after
- Composer runs in quiet, non-interactive mode; your host needs no PHP

Troubleshooting
- Skip hooks in emergencies: git commit --no-verify / git push --no-verify
- Check container logs: docker compose -p smartalloc logs -f wordpress
- Rebuild environment: docker compose -p smartalloc down -v && docker compose -p smartalloc up -d

