param(
  [ValidateSet('infra','all')] [string]$Scope = 'infra',
  [string]$Message = '',
  [switch]$CreatePR,
  [string]$Branch = '',
  [string]$RepoSsh = 'git@github.com:rezahh107/SmartAlloc.git',
  [switch]$SkipCI,
  [switch]$DryRun,
  [switch]$NoRebase,
  [switch]$AllowDirty
)

$ErrorActionPreference = 'Stop'

function note($t){ Write-Host ("== {0}" -f $t) -ForegroundColor Cyan }
function ok($t){ Write-Host ("✔ {0}" -f $t) -ForegroundColor Green }
function warn($t){ Write-Host ("! {0}" -f $t) -ForegroundColor Yellow }
function err($t){ Write-Host ("✖ {0}" -f $t) -ForegroundColor Red }
function run($c){ Write-Host ">> $c"; if (-not $DryRun) { iex $c } }
function fail($code,$msg){ err $msg; exit $code }

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
        if ($DryRun) { Write-Host ("(dryrun) would stage: {0}" -f $path) }
        else { run ("git add -- '{0}'" -f $path) }
        break
      }
    }
  }
}

if (-not $nothingToCommit) {
  if ($Scope -eq 'infra') {
    Stage-ByRules -rules $allowInfra
  } else {
    if ($DryRun) { Write-Host "(dryrun) would: git add -A" }
    else { run 'git add -A' }
  }
}

# 4) Ensure nothing blocked is staged
$stagedRaw = (git diff --cached --name-only) 2>$null
$staged = @()
if ($stagedRaw) { $staged = $stagedRaw -split "`r?`n" | Where-Object { $_ -ne '' } }
foreach ($p in $staged) {
  foreach ($rx in $blocked) {
    if ($p -match $rx) {
      if ($DryRun) { err ("Blocked path would be staged: {0}" -f $p); fail 10 "DryRun detected blocked path" }
      else { fail 10 ("Blocked path staged: {0}. Remove from index and retry." -f $p) }
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
  if ($DryRun) { Write-Host "(dryrun) would: git commit -m ..." }
  else { run ("git commit -m {0}" -f ('"'+$Message.Replace('"','\"')+'"')) }
  $didCommit = $true
}

# 7) Optional: run offline CI
${ciSummary} = ""
${ciPass} = $true
if (-not $SkipCI) {
  note "Running offline CI (tools/offline-ci.ps1)"
  if (Test-Path .\tools\offline-ci.ps1) {
    if ($DryRun) {
      Write-Host "(dryrun) would: run offline-ci.ps1"
    } else {
      & powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\offline-ci.ps1
      $exit = $LASTEXITCODE
      # derive latest artifacts folder
      $base = Join-Path (Get-Location) 'build/ci-local'
      $latest = ''
      if (Test-Path $base) {
        $latest = Get-ChildItem -Path $base -Directory | Sort-Object Name -Descending | Select-Object -First 1 | ForEach-Object { $_.FullName }
      }
      if ($exit -ne 0) { $ciPass = $false }
      $ciSummary = "Offline CI: " + ($(if ($ciPass) { 'PASS' } else { 'FAIL' }))
      if ($latest) { $ciSummary += " | artifacts: $latest" }
      if (-not $ciPass) { warn $ciSummary; fail 20 "Offline CI failed" }
      else { ok $ciSummary }
    }
  } else {
    warn "tools/offline-ci.ps1 not found; skipping CI"
  }
}

# 8) Network: ensure SSH or fallback to HTTPS
note "Ensuring SSH remote & connectivity"
if ($DryRun) {
  Write-Host "(dryrun) would: set origin to $RepoSsh and test ssh"
} else {
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
}

$usedHttps = $false
if ($DryRun) {
  Write-Host "(dryrun) would: git fetch origin"
} else {
  try { note "git fetch"; run 'git fetch origin' }
  catch {
    note "Fetch failed; switching to HTTPS temporarily"; $usedHttps=$true;
    run 'git remote set-url origin https://github.com/rezahh107/SmartAlloc.git';
    run 'git fetch origin'
  }
}

# 9) Rebase & push
if (-not $NoRebase) {
  note "Rebase origin/$Branch"
  if ($DryRun) { Write-Host "(dryrun) would: git rebase origin/$Branch" }
  else {
    try { run ("git rebase origin/{0}" -f $Branch) } catch { fail 30 "Rebase failed" }
  }
} else { warn "Skipping rebase (NoRebase)" }

note "Push branch"
if ($DryRun) {
  Write-Host "(dryrun) would: git push -u origin $Branch"
} else {
  try {
    if ($usedHttps) {
      run ("git push -u origin {0}" -f $Branch)
      run ("git remote set-url origin {0}" -f $RepoSsh)
    } else {
      run ("git push -u origin {0}" -f $Branch)
    }
  } catch { fail 40 "Push failed" }
}

# 10) Optional PR
if ($CreatePR) {
  $title = 'CI reset & repo hygiene (infra-only)'
  if (Get-Command gh -ErrorAction SilentlyContinue) {
    note "Creating PR via gh"
    $body = "Infra-only: CI reset + repo hygiene + service auto-detect + wait-for-DB + script perms/LF. No app code changes."
    if ($ciSummary) { $body = "$body`n`n$ciSummary" }
    try {
      & gh pr create --base main --title $title --body $body
    } catch {
      warn "PR creation may have failed or already exists; attempting to comment summary"
      if ($ciSummary) { try { & gh pr comment --body $ciSummary } catch { warn "Couldn't add PR comment." } }
    }
  } else {
    Write-Host "Open PR in browser:"
    Write-Host ("https://github.com/rezahh107/SmartAlloc/compare/main...{0}?expand=1" -f $Branch)
  }
}

if ($DryRun) { ok "DryRun complete. Branch: $Branch" } else { ok "Ship done. Branch: $Branch" }
