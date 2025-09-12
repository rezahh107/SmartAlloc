Param()

$ErrorActionPreference = "Stop"

function Note($t){ Write-Host "== $t" }
function Info($t){ Write-Host ">> $t" }

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$root = Join-Path (Get-Location) "build/ci-local/$ts"
$logsDir = Join-Path $root "logs"
New-Item -ItemType Directory -Force -Path $logsDir | Out-Null

function Normalize-LF($path){
  if (Test-Path $path) {
    try {
      $raw = Get-Content -Raw -Path $path -ErrorAction Stop
      $raw = $raw -replace "`r`n","`n"
      Set-Content -Path $path -Value $raw -NoNewline
    } catch {}
  }
}

function Step([string]$name, [ScriptBlock]$block){
  $sw = [System.Diagnostics.Stopwatch]::StartNew()
  Note "$name"
  $safe = ($name -replace '[^A-Za-z0-9._-]','_') + ".log"
  $logFile = Join-Path $logsDir $safe
  $ok = $true
  try {
    & $block 2>&1 | Tee-Object -FilePath $logFile | Out-Host
    if ($LASTEXITCODE -ne $null -and $LASTEXITCODE -ne 0) { throw "Exit $LASTEXITCODE" }
  } catch {
    $ok = $false
    Add-Content -Path $logFile -Value ("`nERROR: {0}" -f $_)
    Write-Host ("!! {0} failed: {1}" -f $name, $_) -ForegroundColor Red
  }
  $sw.Stop()
  return [pscustomobject]@{ Name=$name; Pass=$ok; Log=$logFile; Ms=$sw.ElapsedMilliseconds }
}

function Detect-Service([string[]]$candidates){
  $servicesRaw = (& docker compose config --services 2>&1)
  $services = @()
  foreach($ln in ($servicesRaw -split "`r?`n")){
    if ([string]::IsNullOrWhiteSpace($ln)) { continue }
    if ($ln -match ': ' -or $ln -match 'warning' -or $ln -match 'obsolete') { continue }
    $services += $ln.Trim()
  }
  foreach($c in $candidates){ if ($services -contains $c) { return $c } }
  return $null
}

function Wait-Db([string]$db){
  for($i=1; $i -le 120; $i++){
    & docker compose exec -T $db sh -lc 'mysqladmin ping -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent' | Out-Null
    if ($LASTEXITCODE -eq 0) { return $true }
    & docker compose exec -T $db sh -lc 'test -S /var/run/mysqld/mysqld.sock' | Out-Null
    if ($LASTEXITCODE -eq 0) { return $true }
    Start-Sleep -Seconds 2
  }
  return $false
}

# 1) Detect services
$phpCandidates = @('php','php-fpm','wordpress','app','web','backend','fpm','wp','site')
$dbCandidates  = @('db','mysql','mariadb')
$PHP = Detect-Service $phpCandidates
$DB  = Detect-Service $dbCandidates
if (-not $PHP) { Write-Error "Could not detect a PHP-like service."; exit 1 }
if (-not $DB)  { Write-Error "Could not detect a DB-like service."; exit 1 }
Write-Host ("Detected services → PHP: {0} | DB: {1}" -f $PHP,$DB)

$results = @()

# 2) Build
$results += Step "Docker build" { docker compose build --no-cache }

# 3) Up DB and wait
$results += Step "Up DB ($DB)" { docker compose up -d $DB }
$results += Step "Wait DB health" { if (-not (Wait-Db $DB)) { throw "DB not ready after timeout" } }

# 4) Up PHP
$results += Step "Up PHP ($PHP)" { docker compose up -d $PHP }

# 5) Normalize shell scripts (LF) + chmod init
$results += Step "Normalize scripts + chmod" {
  if (Test-Path "docker/init.sh") {
    Normalize-LF "docker/init.sh"
    docker compose run --rm $PHP bash -lc 'chmod +x ./docker/init.sh || true'
  } else { Write-Host "No docker/init.sh found; skipping" }
}

# 6) Composer install (retry, unlimited memory)
$results += Step "Composer install" {
  docker compose run --rm $PHP bash -lc 'export COMPOSER_MEMORY_LIMIT=-1; composer clear-cache || true; composer install -o || composer install -o'
}

# 7) Project init
$results += Step "Project init" {
  if (Test-Path "docker/init.sh") {
    docker compose run --rm $PHP bash -lc './docker/init.sh'
  } else { Write-Host "No docker/init.sh found; skipping" }
}

# 8) PHPUnit
$results += Step "Unit tests (phpunit)" { docker compose run --rm $PHP vendor/bin/phpunit -v }

# 9) Quality selective
$results += Step "Quality selective" { docker compose run --rm $PHP composer run quality:selective }

# 10) Baseline check
$results += Step "Baseline (FOUNDATION)" { docker compose run --rm $PHP php baseline-check --current-phase=FOUNDATION }

# 11) Collect artifacts
Note "Collecting artifacts"
$artDir = Join-Path $root "artifacts"
New-Item -ItemType Directory -Force -Path $artDir | Out-Null
if (Test-Path "build/coverage") { Copy-Item -Recurse -Force "build/coverage" (Join-Path $artDir "coverage") }
foreach($f in @('build/testdox.json','build/site-health.json')){ if (Test-Path $f) { $dest = Join-Path $artDir (Split-Path $f -Leaf); Copy-Item -Force $f $dest } }

# 12) Summary
Write-Host "`nSummary:"
$anyFail = $false
foreach($r in $results){
  $status = if ($r.Pass) { 'PASS' } else { 'FAIL' }
  if (-not $r.Pass) { $anyFail = $true }
  Write-Host ("- {0}: {1} ({2} ms) -> {3}" -f $r.Name, $status, $r.Ms, $r.Log)
}
Write-Host ("Artifacts saved to: {0}" -f $root)

if ($anyFail) { Write-Host "One or more steps failed." -ForegroundColor Red; exit 1 } else { Write-Host "All steps passed." -ForegroundColor Green; exit 0 }

