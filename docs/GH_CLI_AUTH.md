# GH CLI AUTH

This guide explains how to trigger SmartAlloc CI workflow runs via the GitHub CLI.

## Create a Personal Access Token

1. Visit https://github.com/settings/tokens and generate a token.
2. Select **repo** and **workflow** scopes.
3. Copy the token value.

## Authenticate the GitHub CLI

Export the token as `GH_TOKEN`:

```bash
export GH_TOKEN=YOUR_TOKEN
```

Or log in with the token:

```bash
printf '%s\n' "$GH_TOKEN" | gh auth login --with-token
```

Verify access:

```bash
gh auth status --show-token
```

## Dispatch the CI workflow

After authentication you can trigger jobs:

```bash
gh workflow run ci.yml -f job=qa --repo rezahh107/SmartAlloc --json runNumber -q '.runNumber'
```

Replace `qa` with `full` to run the full job.

## Script example

The repository includes `scripts/gh-workflow-run-example.sh` which dispatches a job and prints the run number:

```bash
./scripts/gh-workflow-run-example.sh qa
```

