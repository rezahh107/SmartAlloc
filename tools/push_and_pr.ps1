Param()

$ErrorActionPreference = "Stop"
function Exec($cmd){ Write-Host ">> $cmd"; iex $cmd }

try {
  # Detect repo slug
  $remoteUrl = ""
  try { $remoteUrl = (git remote get-url origin).Trim() } catch { }
  $slug = "rezahh107/SmartAlloc"
  if ($remoteUrl -match "github\.com[:/](?<owner>[^/]+)/(?<repo>[^/]+?)(?:\.git)?$") {
    $slug = "$($Matches['owner'])/$($Matches['repo'])"
  }

  # Detect current branch
  $branch = (git rev-parse --abbrev-ref HEAD).Trim()

  # Check gh presence and auth
  $hasGh = (Get-Command gh -ErrorAction SilentlyContinue) -ne $null
  $ghReady = $false
  if ($hasGh) {
    cmd /c "gh auth status" | Out-Null
    if ($LASTEXITCODE -eq 0) {
      $ghReady = $true
    } else {
      Write-Host "GitHub CLI found but not logged in. Launching login..."
      try { Exec "gh auth login" } catch { Write-Warning "gh auth login error: $_" }
      cmd /c "gh auth status" | Out-Null
      if ($LASTEXITCODE -eq 0) { $ghReady = $true }
    }
  } else {
    Write-Warning "GitHub CLI (gh) not found. You can install it from https://cli.github.com/ or use HTTPS+PAT for push."
  }

  # Ensure SSH key exists and agent is running
  $HOME = [Environment]::GetFolderPath("UserProfile")
  $sshDir = Join-Path $HOME ".ssh"
  if (!(Test-Path $sshDir)) { New-Item -ItemType Directory -Path $sshDir | Out-Null }
  $keyBase = Join-Path $sshDir "id_ed25519"
  if (!(Test-Path $keyBase)) {
    Exec "ssh-keygen -t ed25519 -N \"\" -f `"$keyBase`""
  }
  try { Exec "Set-Service ssh-agent -StartupType Automatic" } catch {}
  try { Exec "Start-Service ssh-agent" } catch {}
  try { Exec "ssh-add `"$keyBase`"" } catch { Write-Warning "ssh-add failed or key already added: $_" }

  # If gh is ready, ensure the public key is registered in GitHub
  if ($ghReady) {
    $title = "SmartAlloc-$([Environment]::MachineName)-" + (Get-Date -Format yyyyMMdd-HHmm)
    try {
      Exec "gh ssh-key add `"$keyBase.pub`" -t `"$title`""
    } catch {
      Write-Host "gh ssh-key add skipped or failed (possibly already added)."
    }
  }

  # Set remote to SSH
  $sshRemote = "git@github.com:$slug.git"
  try { Exec "git remote set-url origin $sshRemote" } catch { try { Exec "git remote add origin $sshRemote" } catch {} }

  # Test SSH connectivity to GitHub
  $sshOk = $false
  try {
    $output = & ssh -o StrictHostKeyChecking=accept-new -T git@github.com 2>&1
    $exit = $LASTEXITCODE
    if ($output -match "successfully authenticated" -or $exit -eq 1) { $sshOk = $true }
    else { Write-Warning ("SSH test did not confirm authentication. Exit={0}. Output: {1}" -f $exit, $output) }
  } catch { Write-Warning "SSH test failed: $_" }

  # Push branch
  $pushed = $false
  $pushAuth = if ($ghReady -or $sshOk) { "ssh" } else { "https" }
  try {
    Exec "git push -u origin $branch"
    $pushed = $true
  } catch {
    Write-Warning "git push over SSH failed: $_"
    $pushAuth = "https"
    $httpsRemote = "https://github.com/$slug.git"
    try { Exec "git remote set-url origin $httpsRemote" } catch {}
    Write-Host "Falling back to HTTPS. If prompted, use a GitHub PAT."
    try {
      Exec "git push -u origin $branch"
      $pushed = $true
    } catch {
      Write-Error "git push failed over HTTPS as well. Please authenticate (gh login or set up PAT) and rerun."
    }
  }

  # Create PR if gh is ready
  $prUrl = ""
  if ($hasGh) {
    cmd /c "gh auth status" | Out-Null
    if ($LASTEXITCODE -eq 0) {
      $bodyFile = ".github/PULL_REQUEST_BODY.md"
      if (!(Test-Path $bodyFile)) {
        $tempBody = [System.IO.Path]::Combine([System.IO.Path]::GetTempPath(), "smartalloc_pr_body.md")
        @"
CI reset & repo hygiene (infra-only)

Changes:
- Auto-detect PHP and DB services in Makefile and CI
- Wait for DB health before running composer/init/tests
- Normalize shell script line endings (LF) and set execute bit
- Scope: infra only; no application code changes

Workflow:
- .github/workflows/ci.yml updated to use detected services and wait for DB
- Makefile.docker updated with DB detection and wait-db target
"@ | Set-Content -Path $tempBody -NoNewline
        $bodyFile = $tempBody
      }
      try {
        Exec "gh pr create --base main --title \"CI reset & repo hygiene (infra-only)\" --body-file `"$bodyFile`""
      } catch {
        Write-Warning "gh pr create failed or PR may already exist: $_"
      }
      try {
        $prUrl = (& gh pr view --json url --jq .url).Trim()
      } catch {
        try { $prUrl = (& gh pr view --json url -q .url).Trim() } catch {}
      }
    }
  }

  # Summary
  Write-Host "Detected repo: $slug"
  Write-Host "Branch: $branch"
  $authSummary = if ($ghReady) { "gh+ssh" } elseif ($sshOk) { "ssh" } else { $pushAuth }
  Write-Host "Auth: $authSummary"
  Write-Host ("Pushed: {0}" -f ($(if ($pushed) {"yes"} else {"no"})))
  if ($prUrl) { Write-Host "PR: $prUrl" } else { Write-Host "PR: run 'gh pr create --base main --title \"CI reset & repo hygiene (infra-only)\"' once authenticated" }

} catch {
  Write-Error $_
  exit 1
}

