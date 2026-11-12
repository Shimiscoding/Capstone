$demoProject = Split-Path -Parent $PSScriptRoot
$demoStorage = Join-Path $demoProject 'storage\mock-defense'
$demoDatabase = Join-Path $demoStorage 'demo.sqlite'
$demoEnvFile = Join-Path $demoProject '.env.mock-defense'

foreach ($demoDirectory in @('app\private', 'app\public', 'framework\views', 'framework\sessions', 'logs')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $demoStorage $demoDirectory) -ErrorAction Stop | Out-Null
}
if (-not (Test-Path -LiteralPath $demoDatabase -PathType Leaf)) {
    New-Item -ItemType File -Path $demoDatabase -ErrorAction Stop | Out-Null
}
if (-not (Test-Path -LiteralPath $demoEnvFile -PathType Leaf)) {
    $demoKey = New-Object byte[] 32
    $demoRandom = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $demoRandom.GetBytes($demoKey) } finally { $demoRandom.Dispose() }
    $demoEncodedKey = [Convert]::ToBase64String($demoKey)
    $demoDatabaseForEnv = $demoDatabase.Replace('\', '/')
    Set-Content -LiteralPath $demoEnvFile -Encoding ascii -ErrorAction Stop -Value @"
APP_NAME="TOMECO Mock Defense"
APP_ENV=mock-defense
APP_KEY=base64:$demoEncodedKey
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_MAINTENANCE_DRIVER=file
DB_CONNECTION=sqlite
DB_DATABASE="$demoDatabaseForEnv"
LOG_CHANNEL=single
SESSION_DRIVER=file
SESSION_COOKIE=tomeco_mock_defense
CACHE_STORE=database
BROADCAST_CONNECTION=null
QUEUE_CONNECTION=sync
MAIL_MAILER=array
"@
}

# Override inherited database settings and bypass caches created by a normal Laravel session.
$env:APP_ENV = 'mock-defense'
$env:APP_KEY = (Select-String -LiteralPath $demoEnvFile -Pattern '^APP_KEY=(base64:[A-Za-z0-9+/=]+)$').Matches.Groups[1].Value
if (-not $env:APP_KEY) { throw "Missing demo APP_KEY in $demoEnvFile" }
$env:LARAVEL_STORAGE_PATH = $demoStorage
$env:VIEW_COMPILED_PATH = Join-Path $demoStorage 'framework\views'
$env:APP_CONFIG_CACHE = Join-Path $demoStorage 'config.php'
$env:APP_ROUTES_CACHE = Join-Path $demoStorage 'routes.php'
$env:APP_EVENTS_CACHE = Join-Path $demoStorage 'events.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $demoDatabase
$env:DB_URL = $null
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = 'tomeco_mock_defense'
$env:CACHE_STORE = 'database'
$env:BROADCAST_CONNECTION = 'null'
$env:MAIL_MAILER = 'array'
$env:QUEUE_CONNECTION = 'sync'
