## Summary
Infra-only refresh: CI reset via Docker Compose, repo hygiene, and local mirrors for tests/quality.

## Scope
Infra-only changes to: .github/workflows/ci.yml, .gitignore, .env.example, Makefile.docker, README.md

## Preflight Status
- ✅ Compose build: Pass
- ✅ Compose up db: Pass
- ❌ Compose up php: Fail
- ❌ Composer install: Fail
- ❌ Project init: Fail
- ❌ Unit tests: Fail
- ❌ Quality selective: Fail
- ❌ Baseline check: Fail

### Compose build — Pass
``\n#8 exporting config sha256:1fc5927bab5708a9946f3ee8802d52138437eac6185577a30cd2bf8cd6e4ad8b 0.0s done
#8 exporting attestation manifest sha256:a6b7ed14a8e6a23d356439a697e2c9ffe2dd8622771f1c4b0184ef9858dd382d 0.0s done
#8 exporting manifest list sha256:0fa5cde8f6076d47b91246e3f79ee28f93b27a7069145ce1d5a8a101c65f02b6 0.0s done
#8 naming to docker.io/library/smartalloc-wordpress:latest
#8 naming to docker.io/library/smartalloc-wordpress:latest done
#8 unpacking to docker.io/library/smartalloc-wordpress:latest 0.0s done
#8 DONE 0.3s

#9 resolving provenance for metadata file
#9 DONE 0.0s
 smartalloc-wordpress  Built\n``

### Compose up db — Pass
``\n 1ca2ca504238 Pull complete 
 390885da77e4 Pull complete 
 db Pulled 
 Network smartalloc_default  Creating
 Network smartalloc_default  Created
 Volume "smartalloc_db_data"  Creating
 Volume "smartalloc_db_data"  Created
 Container smartalloc-db-1  Creating
 Container smartalloc-db-1  Created
 Container smartalloc-db-1  Starting
 Container smartalloc-db-1  Started\n``

### Compose up php — Fail
``\ntime="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_DATABASE\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_PASSWORD\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Check PHP service name and health; try: docker compose logs php

### Composer install — Fail
``\ntime="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_PASSWORD\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_ENVIRONMENT_TYPE\" variable is not set. Defaulting to a 
blank string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Clear composer cache: docker compose run --rm php composer clear-cache

### Project init — Fail
``\ntime="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_PASSWORD\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_ENVIRONMENT_TYPE\" variable is not set. Defaulting to a 
blank string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Verify ./docker/init.sh exists and is executable

### Unit tests — Fail
``\ntime="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_ENVIRONMENT_TYPE\" variable is not set. Defaulting to a 
blank string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_DATABASE\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:44+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Run focused tests or inspect logs: docker compose run --rm php vendor/bin/phpunit -v

### Quality selective — Fail
``\ntime="2025-09-13T00:15:45+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"MYSQL_DATABASE\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"MYSQL_PASSWORD\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Fix lint/static errors, then re-run the selective gate

### Baseline check — Fail
``\ntime="2025-09-13T00:15:45+03:30" level=warning msg="The \"MYSQL_USER\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"MYSQL_PASSWORD\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"WP_ENVIRONMENT_TYPE\" variable is not set. Defaulting to a 
blank string."
time="2025-09-13T00:15:45+03:30" level=warning msg="The \"WP_DEBUG\" variable is not set. Defaulting to a blank 
string."
time="2025-09-13T00:15:45+03:30" level=warning msg="E:\\SmartLoc\\SmartAlloc\\docker-compose.yml: the attribute 
`version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
no such service: php\n``
> Suggestion: Ensure baseline phase FOUNDATION passes locally: docker compose run --rm php php baseline-check --current-phase=FOUNDATION


## Checklist
- [ ] CI passes on this PR (phpunit / quality:selective / baseline-check)
- [ ] No application code changed (infra-only)
- [ ] Coverage meets project target (≥80% if applicable)
- [ ] Baseline phase: FOUNDATION — PASS
- [ ] .env not committed; endor/ and wp-content/uploads/ ignored
