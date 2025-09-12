param(
  [ValidateSet('infra','all')] [string]$Scope = 'infra',
  [string]$Message = '',
  [switch]$CreatePR,
  [string]$Branch = '',
  [string]$RepoSsh = 'git@github.com:rezahh107/SmartAlloc.git',
  [switch]$SkipCI
)

$ErrorActionPreference = 'Stop'
function note($t){ Write-Host "== $t" }
function run($c){ Write-Host ">> $c"; iex $c }

# 0) Branch detection
if (-not $Branch) {
  $Branch = (git rev-parse --abbrev-ref HEAD).Trim()
  if (-not $Branch) { throw "Cannot detect current branch." }
}

# 1) Safety: disallow committing sensitive paths
$blocked = @(
  '\.env($|/)', '^vendor/', '^wp-content/uploads/', '^backup/'
)

# 2) Build allowlist for "infra" scope
$allowInfra = @(
  '^\.github/', '^tools/', '^docker/.*\.sh$', '^\.vscode/', '^Makefile\.docker$',
  '^\.gitignore$', '^\.gitattributes$', '^README\.md$'
)

# 3) Stage files according to scope
note "Staging changes (Scope=$Scope)"
# refresh index
git update-index -q --refresh | Out-Null
$changedRaw = (git status --porcelain) 2>$null
$changed = @()
if ($changedRaw) {
  foreach($ln in ($changedRaw -split "`r?`n")){
    if ([string]::IsNullOrWhiteSpace($ln)) { continue }
    $changed += $ln
  }
}
if (-not $changed -or $changed.Count -eq 0) { note "No changes to commit."; $nothingToCommit = $true } else { $nothingToCommit = $false }

function Normalize-PathFromStatus([string]$line){
  if ($line.Length -lt 4) { return $null }
  $p = $line.Substring(3)
  if ($p -match ' -> ') { $p = $p -replace '.* ->\s*','' }
  return $p
}

function Stage-ByRules {
  param([string[]]$rules)
  foreach ($line in $changed) {
    $path = Normalize-PathFromStatus $line
    if (-not $path) { continue }
    foreach ($rx in $rules) {
      if ($path -match $rx) {
        run ("git add -- '{0}'" -f $path)
        break
      }
    }
  }
}

if (-not $nothingToCommit) {
  if ($Scope -eq 'infra') {
    Stage-ByRules -rules $allowInfra
  } else {
    run 'git add -A'
  }
}

# 4) Ensure nothing blocked is staged
$stagedRaw = (git diff --cached --name-only) 2>$null
$staged = @()
if ($stagedRaw) { $staged = $stagedRaw -split "`r?`n" | Where-Object { $_ -ne '' } }
foreach ($p in $staged) {
  foreach ($rx in $blocked) {
    if ($p -match $rx) {
      throw "Blocked path staged: $p. Remove from index and retry."
    }
  }
}

# 5) If nothing staged, continue but skip commit
$didCommit = $false
if (-not $staged -or $staged.Count -eq 0) {
  note "Nothing staged; skipping commit."
} else {
  # 6) Guess commit type if message empty
  if (-not $Message) {
    $types = @{
      'ci'     = @('\.github/','Makefile\.docker','tools/offline-ci\.ps1','tools/ship-auto\.ps1','final-path-b\.ps1')
      'chore'  = @('\.gitignore','\.gitattributes','\.vscode/')
      'docs'   = @('README\.md')
      'test'   = @('^tests/')
    }
    $pick = 'chore'
    foreach ($k in $types.Keys) {
      if ($staged | Where-Object { $_ -match ($types[$k] -join '|') }) { $pick = $k; break }
    }
    $Message = "$pick: automated ship (infra)"
  }
  note "Commit: $Message"
  run ("git commit -m {0}" -f ('"'+$Message.Replace('"','\"')+'"'))
  $didCommit = $true
}

# 7) Optional: run offline CI
if (-not $SkipCI) {
  note "Running offline CI (tools/offline-ci.ps1)"
  if (Test-Path .\tools\offline-ci.ps1) {
    powershell -ExecutionPolicy Bypass -File .\tools\offline-ci.ps1
  } else {
    Write-Host "⚠️ tools/offline-ci.ps1 not found; skipping CI"
  }
}

# 8) Network: ensure SSH or fallback to HTTPS
note "Ensuring SSH remote & connectivity"
run ("git remote set-url origin {0}" -f $RepoSsh)
$sshOk = $true
try { run 'ssh -T git@github.com' } catch { $sshOk = $false }
if (-not $sshOk) {
  # Use port 443
  $cfg="$env:USERPROFILE\.ssh\config"
  if (!(Test-Path (Split-Path $cfg))) { New-Item -ItemType Directory -Path (Split-Path $cfg) | Out-Null }
  @"
Host github.com
  HostName ssh.github.com
  Port 443
  User git
  IdentityFile ~/.ssh/id_ed25519
"@ | Out-File -Encoding ascii $cfg
  try { run 'ssh -T git@github.com'; $sshOk = $true } catch { $sshOk = $false }
}

$usedHttps = $false
try { note "git fetch"; run 'git fetch origin' }
catch {
  note "Fetch failed; switching to HTTPS temporarily"; $usedHttps=$true;
  run 'git remote set-url origin https://github.com/rezahh107/SmartAlloc.git';
  run 'git fetch origin'
}

# 9) Rebase & push
note "Rebase origin/$Branch"
run ("git rebase origin/{0}" -f $Branch)

note "Push branch"
if ($usedHttps) {
  run ("git push -u origin {0}" -f $Branch)
  run ("git remote set-url origin {0}" -f $RepoSsh)
} else {
  run ("git push -u origin {0}" -f $Branch)
}

# 10) Optional PR
if ($CreatePR) {
  $title = 'CI reset & repo hygiene (infra-only)'
  if (Get-Command gh -ErrorAction SilentlyContinue) {
    note "Creating PR via gh"
    & gh pr create --base main --title $title --body "Infra-only: CI reset + repo hygiene + service auto-detect + wait-for-DB + script perms/LF. No app code changes."
  } else {
    Write-Host "Open PR in browser:"
    Write-Host ("https://github.com/rezahh107/SmartAlloc/compare/main...{0}?expand=1" -f $Branch)
  }
}

Write-Host "`n✅ Ship done. Branch: $Branch"
