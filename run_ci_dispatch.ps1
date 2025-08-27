$ErrorActionPreference = 'Stop'

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    Write-Error 'GitHub CLI (gh) is required'
    exit 1
}

if (-not $env:GH_TOKEN) {
    Write-Error 'GH_TOKEN is required'
    exit 1
}

try {
    gh auth status *> $null
} catch {
    $LASTEXITCODE = 1
}

if ($LASTEXITCODE -ne 0) {
    Write-Output $env:GH_TOKEN | gh auth login --with-token *> $null
}

function Run-CI {
    param(
        [Parameter(Mandatory=$true)][string]$Job,
        [string[]]$ExtraArgs
    )
    $args = @('workflow','run','ci.yml','-f',"job=$Job")
    if ($ExtraArgs) { $args += $ExtraArgs }
    $args += @('--repo','rezahh107/SmartAlloc','--json','runNumber,url','-q','.')
    $info = gh @args | ConvertFrom-Json
    Write-Output "$Job run: #$($info.runNumber) $($info.url)"
}

Run-CI -Job 'qa'
Run-CI -Job 'full' -ExtraArgs @('-f','inject_ci_failure=true')
