[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$taskRepo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskCheck = Join-Path ([System.IO.Path]::GetTempPath()) ('aquadissect-release-check-' + [guid]::NewGuid().ToString('N'))
$taskEnvironment = @{}
$taskKeys = @('APP_ENV','APP_KEY','APP_CONFIG_CACHE','APP_PACKAGES_CACHE','APP_SERVICES_CACHE','VIEW_COMPILED_PATH','DB_CONNECTION','DB_DATABASE','DB_URL','APP_AUTO_MIGRATE','CLONE_ENABLED','SESSION_DRIVER','CACHE_STORE','MAIL_MAILER','QUEUE_CONNECTION','GIT_AUTHOR_NAME','GIT_AUTHOR_EMAIL','GIT_COMMITTER_NAME','GIT_COMMITTER_EMAIL')
foreach ($taskKey in $taskKeys) { $taskEnvironment[$taskKey] = [Environment]::GetEnvironmentVariable($taskKey, 'Process') }

function Invoke-ReleaseCheck([string]$Command, [string[]]$Arguments) {
    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Required release check failed: $Command" }
}

try {
    New-Item -ItemType Directory -Path $taskCheck | Out-Null
    Push-Location $taskRepo
    try {
        # Copy only source-controlled and reviewable new files. Never copy a real environment or database.
        $taskFiles = @(& git ls-files --cached --others --exclude-standard)
        if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect release files.' }
        foreach ($taskFile in ($taskFiles | Sort-Object -Unique)) {
            if ($taskFile -match '^(\.env($|\.)|storage/|output/|vendor/|node_modules/|database/.*\.(sqlite|db)$)') { continue }
            $taskSource = Join-Path $taskRepo $taskFile
            if (-not (Test-Path -LiteralPath $taskSource -PathType Leaf)) { continue }
            $taskTarget = [IO.Path]::GetFullPath((Join-Path $taskCheck $taskFile))
            if (-not $taskTarget.StartsWith($taskCheck + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid source path.' }
            New-Item -ItemType Directory -Path (Split-Path -Parent $taskTarget) -Force | Out-Null
            Copy-Item -LiteralPath $taskSource -Destination $taskTarget
        }
    } finally { Pop-Location }
    $env:GIT_AUTHOR_NAME='Project Contributor'; $env:GIT_COMMITTER_NAME='Project Contributor'
    $env:GIT_AUTHOR_EMAIL='project-contributor@users.noreply.github.com'; $env:GIT_COMMITTER_EMAIL=$env:GIT_AUTHOR_EMAIL
    Push-Location $taskCheck
    try {
        Invoke-ReleaseCheck 'git' @('init','--quiet')
        Invoke-ReleaseCheck 'git' @('add','-A')
        Invoke-ReleaseCheck 'git' @('-c','commit.gpgsign=false','commit','--quiet','-m','Isolated release validation snapshot')
        # Validate the committed bytes that source downloads contain, including normalized line endings.
        Invoke-ReleaseCheck 'git' @('-c','core.autocrlf=false','checkout-index','--all','--force')
    } finally { Pop-Location }
    foreach ($taskDirectory in @('bootstrap/cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/framework/testing','storage/logs','storage/app/private')) {
        $taskPath = Join-Path $taskCheck $taskDirectory
        New-Item -ItemType Directory -Path $taskPath -Force | Out-Null
        (Get-Item -LiteralPath $taskPath).Attributes = (Get-Item -LiteralPath $taskPath).Attributes -band (-bnot [IO.FileAttributes]::ReadOnly)
    }
    $env:APP_ENV='testing'
    $taskRandom = New-Object byte[] 32
    $taskRng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $taskRng.GetBytes($taskRandom); $taskRng.Dispose()
    $env:APP_KEY='base64:'+[Convert]::ToBase64String($taskRandom)
    $env:APP_CONFIG_CACHE='bootstrap/cache/release-config.php'
    $env:APP_PACKAGES_CACHE='bootstrap/cache/release-packages.php'
    $env:APP_SERVICES_CACHE='bootstrap/cache/release-services.php'
    $env:VIEW_COMPILED_PATH=Join-Path $taskCheck 'storage/framework/views'
    $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:DB_URL=''
    $env:APP_AUTO_MIGRATE='false'; $env:CLONE_ENABLED='true'
    $env:SESSION_DRIVER='array'; $env:CACHE_STORE='array'; $env:MAIL_MAILER='array'; $env:QUEUE_CONNECTION='sync'
    Push-Location $taskCheck
    try {
        Invoke-ReleaseCheck 'composer' @('install','--no-scripts','--prefer-dist','--no-interaction','--no-progress')
        Invoke-ReleaseCheck 'npm.cmd' @('ci','--no-audit','--no-fund')
        Invoke-ReleaseCheck 'npm.cmd' @('run','build')
        Invoke-ReleaseCheck 'php' @('vendor/bin/phpunit','--no-progress')
        Invoke-ReleaseCheck 'node' @('scripts/test-js.mjs')
        $taskGitBash = Join-Path $env:ProgramFiles 'Git/bin/bash.exe'
        if (-not (Test-Path -LiteralPath $taskGitBash)) { $taskGitBash = (Get-Command bash -ErrorAction Stop).Source }
        Invoke-ReleaseCheck $taskGitBash @('tests/deployment/release-recovery.sh')
    } finally { Pop-Location }
    Write-Host 'Required PHP, JavaScript and production-build checks passed in isolated QA.'
} finally {
    foreach ($taskKey in $taskKeys) { [Environment]::SetEnvironmentVariable($taskKey, $taskEnvironment[$taskKey], 'Process') }
    # Keep this private QA folder for diagnosis. It contains no production environment or student data.
}
