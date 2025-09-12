<#
  final-path-b.ps1 — Path B (stash) flow with SSH(22→443) and HTTPS fallback
  Run from repo root in PowerShell
#>

param(
  [string]$Branch = "repair/ci-reset-20250912-2350",
  [string]$RepoSsh = "git@github.com:rezahh107/SmartAlloc.git"
)

$ErrorActionPreference = "Stop"

# Optional logging to capture the run transcript
$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$log = "build\ship-logs\final-path-b-$ts.log"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
try { Start-Transcript -Path $log -NoClobber | Out-Null } catch {}

function Note($t){ Write-Host "== $t" }
function Run($c){ Write-Host ">> $c"; iex $c }

# 0) Pre-checks
Note "Pre-checks"
Run 'git status'
Run 'git remote -v'

# 1) Stash + clean
Note "Stash & clean"
Run 'git stash -u -m "pre-push stash"'
Run 'git config core.ignorecase true'
Run 'git reset --hard'

# 2) Ensure SSH remote
Run "git remote set-url origin $RepoSsh"

# 3) Try SSH on port 22 → if fails, switch to 443
function Ensure-SSH443 {
  $cfg = "$env:USERPROFILE\.ssh\config"
  if (!(Test-Path (Split-Path $cfg))) { New-Item -ItemType Directory -Path (Split-Path $cfg) | Out-Null }
  $block = @"
Host github.com
  HostName ssh.github.com
  Port 443
  User git
  IdentityFile ~/.ssh/id_ed25519
"@
  if (-not (Test-Path $cfg) -or -not ((Get-Content $cfg -Raw) -match 'ssh\.github\.com')) {
    $block | Out-File -Encoding ascii $cfg
  }
}

$sshOK = $false
try { Run 'ssh -T git@github.com'; $sshOK = $true } catch { $sshOK = $false }
if (-not $sshOK) { Note "SSH: falling back to port 443"; Ensure-SSH443; try { Run 'ssh -T git@github.com'; $sshOK = $true } catch { $sshOK = $false } }

# 4) Fetch (SSH preferred; else temporary HTTPS)
$usedHttps = $false
try {
  Note "git fetch (SSH)"; Run 'git fetch origin'
} catch {
  Note "Fetch timed out — trying HTTPS + PAT"; $usedHttps = $true
  Run 'git remote set-url origin https://github.com/rezahh107/SmartAlloc.git'
  Run 'git fetch origin'  # Git will prompt for PAT if required
}

# 5) Rebase on remote branch
Note "Rebase on remote"
Run "git rebase origin/$Branch"

# 6) Push (use same transport used for fetch)
Note "Push branch"
if ($usedHttps) {
  Run "git push -u origin $Branch"
  # Restore SSH remote for next runs
  Run "git remote set-url origin $RepoSsh"
} else {
  Run "git push -u origin $Branch"
}

# 7) Bring back stashed changes
Note "Stash pop"
try { Run 'git stash pop' } catch { Write-Host "⚠️ Resolve conflicts → git add … → git commit" }

# 8) Case-collision quick fix (only if both paths exist)
$lower = 'tests\unit'
$upper = 'tests\Unit\Services\MetricsTest.php'
if ((Test-Path $lower) -and (Test-Path $upper)) {
  Note "Fixing Windows case-collision"
  Run 'git rm --cached -r tests/unit 2>$null'
  if (Test-Path $lower) { Remove-Item -Recurse -Force $lower }
  Run 'git checkout -- tests/Unit/Services/MetricsTest.php 2>$null'
  Run 'git commit -m "test: normalize test path casing on Windows (drop tests/unit duplicate)"'
  # re-sync after fix
  try { Run 'git fetch origin' } catch { }
  Run "git rebase origin/$Branch"
  Run "git push -u origin $Branch"
}

Write-Host "`n✅ Done. Open PR:"
Write-Host '  gh pr create --base main --title "CI reset & repo hygiene (infra-only)" --body "Infra-only: CI reset + repo hygiene + service auto-detect + wait-for-DB + script perms/LF. No app code changes."'
Write-Host '  یا: https://github.com/rezahh107/SmartAlloc/compare/main...repair/ci-reset-20250912-2350?expand=1'

try { Stop-Transcript | Out-Null } catch {}
Write-Host "Log saved → $log"
