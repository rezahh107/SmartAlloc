# Rollback Playbook

## 1. Select Tag
- List tags: `git tag --sort=-v:refname | head`
- Choose prior GA tag (e.g., `v1.0.0`).

## 2. Download & Verify
- Fetch artifact:
  ```bash
  curl -LO https://downloads.wordpress.org/plugin/smart-alloc.<TAG>.zip
  unzip -q smart-alloc.<TAG>.zip
  ```
- Verify checksums using manifest:
  ```bash
  sha256sum --check artifacts/dist/manifest.json
  ```

## 3. WordPress.org Revert Checklist
- Update `readme.txt` stable tag to `<TAG>`.
- Commit and push tag: `git tag -f <TAG> && git push origin <TAG> --force`.
- Upload package via wp.org SVN.

## 4. Data Safety
- No database schema changes in this release; rollback is data-safe.

## 5. Logs & Monitoring
- Review error logs and APM dashboards.
- Confirm circuit breaker state and export queue depth.
- Link to dashboards as needed.
