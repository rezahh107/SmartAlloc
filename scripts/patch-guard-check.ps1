param(
  [int]$MaxFiles = 10,
  [int]$MaxLines = 200
)

if ($env:PATCH_GUARD_MAX_FILES) { $MaxFiles = [int]$env:PATCH_GUARD_MAX_FILES }
if ($env:PATCH_GUARD_MAX_LINES) { $MaxLines = [int]$env:PATCH_GUARD_MAX_LINES }

function Get-UpstreamRef {
  $up = (git rev-parse --abbrev-ref --symbolic-full-name "@{u}" 2>$null)
  if ([string]::IsNullOrWhiteSpace($up)) { return "HEAD~" } else { return $up }
}

$ErrorActionPreference = "Stop"
$upstream = Get-UpstreamRef

# List changed files (Added/Copy/Modify/Rename)
$files = (git diff --name-only --diff-filter=ACMR $upstream...HEAD) | Where-Object { $_ -ne "" }
$filesCount = ($files | Measure-Object).Count

# Sum of added lines
$addedLines = (git diff --numstat $upstream...HEAD | ForEach-Object {
  $parts = $_ -split "`t"
  if ($parts.Length -ge 1) { [int]($parts[0] -as [int]) } else { 0 }
}) | Measure-Object -Sum
$added = $addedLines.Sum

if ($filesCount -le $MaxFiles -and $added -le $MaxLines) {
  Write-Host "Patch Guard PASS ($filesCount files, $added lines) limits: files<=$MaxFiles, lines<=$MaxLines"
  exit 0
} else {
  Write-Error "Patch Guard FAIL ($filesCount files, $added lines) limits: files<=$MaxFiles, lines<=$MaxLines"
  exit 1
}
