# Run from Laragon Terminal in the folder that contains this file:
#   powershell -ExecutionPolicy Bypass -File .\setup.ps1
$ErrorActionPreference = 'Stop'
$Root = $PSScriptRoot
$BE = Join-Path $Root 'backend'
$FE = Join-Path $Root 'frontend'
$Db = 'code_master'
$Model = 'qwen2.5-coder:14b'
$Utf8 = New-Object Text.UTF8Encoding($false)

function Need($cmd) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) { throw "Missing '$cmd'. Use Laragon > Terminal and install PHP, Composer, Node." }
}
function Set-Env($key, $value) {
    $f = Join-Path $BE '.env'
    $txt = [IO.File]::ReadAllText($f)
    if ($txt -match "(?m)^#?\s*$key=") { $txt = [regex]::Replace($txt, "(?m)^#?\s*$key=.*$", "$key=$value") }
    else { $txt = $txt.TrimEnd() + "`n$key=$value`n" }
    [IO.File]::WriteAllText($f, $txt, $Utf8)
}

'php', 'composer', 'node', 'npm' | ForEach-Object { Need $_ }

# 1. backend: Laravel framework (our files in backend\ are kept, only missing files are added)
if (-not (Test-Path "$BE\artisan")) {
    Write-Host '==> Creating Laravel (a few minutes)...'
    $tmp = Join-Path $Root 'laravel-tmp'
    if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
    composer create-project laravel/laravel $tmp --prefer-dist --no-interaction
    robocopy $tmp $BE /E /XC /XN /XO /NFL /NDL /NJH /NJS /NP | Out-Null
    Remove-Item $tmp -Recurse -Force
}

# 2. register routes/api.php in bootstrap/app.php
$boot = "$BE\bootstrap\app.php"
$txt = [IO.File]::ReadAllText($boot)
if ($txt -notmatch "routes/api\.php") {
    Write-Host '==> Enabling routes/api.php'
    $txt = [regex]::Replace($txt, "(?m)^(\s*)web:\s*__DIR__\s*\.\s*'/\.\./routes/web\.php',", "`$1web: __DIR__.'/../routes/web.php',`n`$1api: __DIR__.'/../routes/api.php',")
    [IO.File]::WriteAllText($boot, $txt, $Utf8)
    if ($txt -notmatch "routes/api\.php") { Write-Warning "Could not edit bootstrap\app.php automatically. Add this line under the 'web:' line inside withRouting(): api: __DIR__.'/../routes/api.php'," }
}

Write-Host '==> Writing backend\.env'
Set-Env 'APP_NAME' 'CODE-MASTER'
Set-Env 'DB_CONNECTION' 'mysql'
Set-Env 'DB_HOST' '127.0.0.1'
Set-Env 'DB_PORT' '3306'
Set-Env 'DB_DATABASE' $Db
Set-Env 'DB_USERNAME' 'root'
Set-Env 'DB_PASSWORD' ''
Set-Env 'OLLAMA_HOST' 'http://127.0.0.1:11434'
Set-Env 'OLLAMA_MODEL' $Model

Write-Host "==> Creating database $Db (Laragon MySQL must be running)"
try { mysql -uroot -e "CREATE DATABASE IF NOT EXISTS $Db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; if ($LASTEXITCODE -ne 0) { throw 'mysql failed' } }
catch { Write-Warning "Could not create database. Start MySQL in Laragon, create '$Db' in HeidiSQL, then re-run." }

Set-Location $BE
php artisan config:clear
php artisan migrate --force

# 3. frontend: Vue + Tailwind
Write-Host '==> Installing frontend packages'
Set-Location $FE
npm install vue @fontsource/orbitron @fontsource/jetbrains-mono
npm install -D vite @vitejs/plugin-vue @tailwindcss/vite tailwindcss

# 4. Ollama + git
if (Get-Command ollama -ErrorAction SilentlyContinue) {
    if (-not ((ollama list) -match [regex]::Escape($Model))) { Write-Host "==> Pulling $Model (about 9 GB)..."; ollama pull $Model }
} else { Write-Warning 'Ollama not found. Install from https://ollama.com/download then: ollama pull qwen2.5-coder:14b' }

Set-Location $Root
if (-not (Test-Path "$Root\.git")) { git init | Out-Null }
Write-Host 'Done. Start with start-dev.bat'
