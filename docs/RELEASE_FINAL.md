# Release Finalization

Quick GA finalization flow:

```bash
bash scripts/release-finalizer.sh
cat artifacts/ga/GA_READY.txt
bash scripts/tag-dry-run.sh
```

The finalizer stitches existing distribution and QA helpers into a single advisory
run. Each step is optional and the script never fails if a tool or artifact is
missing. Outputs map to the GA checklist:

- `artifacts/dist/manifest.json` and `artifacts/dist/sbom.json` provide release integrity.
- `artifacts/qa/go-no-go.json` feeds `GA_READY.txt` with REST/SQL/license/secrets counts and coverage when available.
- `artifacts/dist/release-notes.md` captures changelog excerpts and QA signals.

Use the generated `GA_READY.txt` to review version, date, go/no-go summary and
signal counts before performing the final tag dry run.

> **Note:** After running the finalizer you may run the Enforcer with
> `--profile=rc` for an advisory sweep or `RUN_ENFORCE=1 --profile=ga --enforce`
> to enable a hard gate.
