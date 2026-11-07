[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)]
    [int] $Port = 8000
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $root
try {
    if (Test-Path -LiteralPath '.env') {
        throw 'Run the demo from a fresh extraction without an existing .env file.'
    }
    foreach ($tool in @('php', 'composer', 'npm.cmd')) {
        Get-Command $tool -ErrorAction Stop | Out-Null
    }

    $runtime = Join-Path $root 'storage/app/private/local-demo'
    $database = Join-Path $runtime 'aquadissect.sqlite'
    foreach ($folder in @($runtime, 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs')) {
        New-Item -ItemType Directory -Path $folder -Force | Out-Null
    }
    if (-not (Test-Path -LiteralPath $database)) {
        Copy-Item -LiteralPath 'demo/aquadissect-demo.sqlite' -Destination $database
    }

    $key = New-Object byte[] 32
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $random.GetBytes($key)
    } finally {
        $random.Dispose()
    }

    $env:APP_NAME = 'AquaDissect'
    $env:APP_ENV = 'local'
    $env:APP_KEY = 'base64:' + [Convert]::ToBase64String($key)
    $env:APP_DEBUG = 'false'
    $env:APP_URL = "http://127.0.0.1:$Port"
    $env:ASSET_URL = $env:APP_URL
    $env:APP_AUTO_MIGRATE = 'true'
    $env:APP_CONFIG_CACHE = Join-Path $runtime 'config.php'
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_URL = ''
    $env:DB_DATABASE = $database
    $env:CLONE_ENABLED = 'false'
    $env:SESSION_DRIVER = 'file'
    $env:SESSION_COOKIE = 'aquadissect_local_demo'
    $env:CACHE_STORE = 'file'
    $env:FILESYSTEM_DISK = 'local'
    $env:SPECIES_PACKAGES_DISK = 'local'
    $env:MAIL_MAILER = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    $env:GOOGLE_AUTH_ENABLED = 'false'
    $env:STUDENT_ID_INITIAL_PASSWORD_ENABLED = 'false'
    $env:STUDENT_PASSWORD_SETUP_ENABLED = 'false'
    $env:STUDENT_SETUP_ENABLED = 'false'

    if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) {
        composer install --no-scripts --prefer-dist --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Composer dependency installation failed.' }
    }
    if (-not (Test-Path -LiteralPath 'node_modules/.bin/vite.cmd')) {
        npm.cmd ci --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { throw 'Node dependency installation failed.' }
    }
    npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'The local application build failed.' }

    Write-Host "Open $env:APP_URL/login after the server starts."
    php artisan serve --host=127.0.0.1 --port=$Port --tries=1 --no-reload
    if ($LASTEXITCODE -ne 0) { throw 'The local demo server could not start.' }
} finally {
    Pop-Location
}
