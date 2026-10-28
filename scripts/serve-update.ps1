[CmdletBinding()]
param(
    [switch] $RunTests,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $CommitMessageParts
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-CommandOutput {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Command,

        [Parameter(Mandatory = $true)]
        [string[]] $Arguments,

        [Parameter(Mandatory = $true)]
        [string] $FailureMessage
    )

    $output = & $Command @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        $rendered = ($output | Out-String).Trim()
        if ($rendered) {
            throw "$FailureMessage`n$rendered"
        }

        throw $FailureMessage
    }

    return ($output | Out-String).Trim()
}

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Command,

        [Parameter(Mandatory = $true)]
        [string[]] $Arguments,

        [Parameter(Mandatory = $true)]
        [string] $FailureMessage
    )

    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw $FailureMessage
    }
}

function Test-GitStateFile {
    param(
        [Parameter(Mandatory = $true)]
        [string] $GitDir,

        [Parameter(Mandatory = $true)]
        [string] $RelativePath
    )

    return Test-Path (Join-Path $GitDir $RelativePath)
}

$scriptDir = Split-Path -Parent $PSCommandPath
$repoRoot = (Resolve-Path (Join-Path $scriptDir '..')).Path

Push-Location $repoRoot
try {
    $releaseNoisePathspecs = @(
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/app/private',
        'output',
        'outputs',
        'tmp',
        '.phpunit.result.cache',
        'vendor',
        'node_modules'
    )

    $diffCheckPathspecs = @('.')
    foreach ($pathspec in $releaseNoisePathspecs) {
        $diffCheckPathspecs += ':!'+$pathspec
    }

    $branch = Get-CommandOutput -Command 'git' -Arguments @('branch', '--show-current') -FailureMessage 'Unable to determine the current branch.'
    if ($branch -ne 'main') {
        throw "Serve_Update requires the 'main' branch. Current branch: $branch"
    }

    $originUrl = Get-CommandOutput -Command 'git' -Arguments @('remote', 'get-url', 'origin') -FailureMessage 'Remote origin is not configured.'
    if ($originUrl -notmatch '^(https://github\.com/BBQ25/aquadissect(\.git)?|git@github\.com:BBQ25/aquadissect(\.git)?)$') {
        throw "Remote origin must point to GitHub repository BBQ25/aquadissect. Current origin: $originUrl"
    }

    $gitDir = Get-CommandOutput -Command 'git' -Arguments @('rev-parse', '--absolute-git-dir') -FailureMessage 'Unable to resolve the git directory.'
    $activeStates = @()

    if (Test-GitStateFile -GitDir $gitDir -RelativePath 'MERGE_HEAD') {
        $activeStates += 'merge'
    }
    if (
        (Test-GitStateFile -GitDir $gitDir -RelativePath 'rebase-apply') -or
        (Test-GitStateFile -GitDir $gitDir -RelativePath 'rebase-merge')
    ) {
        $activeStates += 'rebase'
    }
    if (Test-GitStateFile -GitDir $gitDir -RelativePath 'CHERRY_PICK_HEAD') {
        $activeStates += 'cherry-pick'
    }
    if (Test-GitStateFile -GitDir $gitDir -RelativePath 'REVERT_HEAD') {
        $activeStates += 'revert'
    }

    if ($activeStates.Count -gt 0) {
        $uniqueStates = $activeStates | Select-Object -Unique
        throw "Git operation in progress: $($uniqueStates -join ', '). Finish it before running /Serve_Update."
    }

    Write-Host 'Checking working tree for whitespace errors...'
    Invoke-Checked -Command 'git' -Arguments (@('diff', '--check', '--') + $diffCheckPathspecs) -FailureMessage 'git diff --check failed.'

    Write-Host 'Running PHP syntax smoke checks...'
    Invoke-Checked -Command 'php' -Arguments @('-l', 'public/deploy-hook.php') -FailureMessage 'php -l public/deploy-hook.php failed.'
    Invoke-Checked -Command 'php' -Arguments @('-l', 'public/index.php') -FailureMessage 'php -l public/index.php failed.'
    Invoke-Checked -Command 'php' -Arguments @('-l', 'routes/web.php') -FailureMessage 'php -l routes/web.php failed.'
    Invoke-Checked -Command 'php' -Arguments @('-l', 'routes/console.php') -FailureMessage 'php -l routes/console.php failed.'

    Write-Host 'Running required isolated release validation...'
    & (Join-Path $scriptDir 'check-release.ps1')

    Write-Host 'Staging repository changes...'
    Invoke-Checked -Command 'git' -Arguments @('add', '-A') -FailureMessage 'git add -A failed.'

    & git 'reset' '-q' 'HEAD' '--' @releaseNoisePathspecs 2>$null
    $null = $LASTEXITCODE

    & git 'diff' '--cached' '--quiet'
    if ($LASTEXITCODE -eq 0) {
        Write-Host 'Nothing to release.'
        exit 0
    }
    if ($LASTEXITCODE -ne 1) {
        throw 'Unable to inspect staged changes.'
    }

    $commitMessage = ($CommitMessageParts -join ' ').Trim()
    if ([string]::IsNullOrWhiteSpace($commitMessage)) {
        $commitMessage = 'chore: serve update'
    }

    Write-Host 'Checking staged changes for whitespace errors...'
    Invoke-Checked -Command 'git' -Arguments @('diff', '--cached', '--check') -FailureMessage 'git diff --cached --check failed.'

    Write-Host "Creating release commit: $commitMessage"
    Invoke-Checked -Command 'git' -Arguments @('commit', '-m', $commitMessage) -FailureMessage 'git commit failed.'

    Write-Host 'Pushing release to origin/main...'
    Invoke-Checked -Command 'git' -Arguments @('push', 'origin', 'main') -FailureMessage 'git push origin main failed.'

    $headSha = Get-CommandOutput -Command 'git' -Arguments @('rev-parse', 'HEAD') -FailureMessage 'Unable to resolve the pushed commit SHA.'

    Write-Host "Released commit $headSha"
    Write-Host 'GitHub push completed. aaPanel deployment should proceed through the webhook dispatcher on aquadissect.com.'
}
finally {
    Pop-Location
}
