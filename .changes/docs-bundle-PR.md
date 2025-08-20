docs(build): add include-based docs bundle & composer docs task

- Adds bin/docs_build.php (pure PHP, no deps) and a "composer docs" script to compile a docs bundle via @include.
- Uses tests/TEST_NOTES.md as single source of truth for the coverage matrix.
- No runtime code changes; no new dev-deps; CI remains green; binary artifacts are ignored by git.
