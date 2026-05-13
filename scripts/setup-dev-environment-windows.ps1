#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Setup script for Tito AI development environment on Windows.
.DESCRIPTION
    Installs Laragon 6.0.0, PHP 8.5.6, Composer, pnpm, Python, uv, and configures the project.

.NOTES
    ============================================================
    INSTALLATION INSTRUCTIONS
    ============================================================

    1. Open PowerShell as Administrator:
       - Press Win + X → select "Terminal (Admin)" or "PowerShell (Admin)"

    2. Allow script execution (one-time):
       Set-ExecutionPolicy RemoteSigned -Scope CurrentUser -Force

    3. Navigate to the project folder:
       cd D:\proyectos\itm\app.tito

    4. Run the script:
       .\scripts\setup-dev-environment-windows.ps1

    5. After completion, open a NEW terminal and run:
       composer dev:win

    ============================================================
#>

# Ensure execution policy allows this script
if ((Get-ExecutionPolicy -Scope CurrentUser) -eq "Restricted") {
    Set-ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
    Write-Host "Execution policy set to RemoteSigned." -ForegroundColor Green
}

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# --- Configuration ---
$LaragonInstaller = "https://github.com/leokhoa/laragon/releases/download/6.0.0/laragon-wamp.exe"
$PhpZip = "https://windows.php.net/downloads/releases/php-8.5.6-nts-Win32-vs17-x64.zip"
$VcRedist = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
$ComposerInstaller = "https://getcomposer.org/Composer-Setup.exe"
$LaragonPath = "C:\laragon"
$PhpPath = "$LaragonPath\bin\php\php-8.5.6-nts-Win32-vs17-x64"
$TempDir = "$env:TEMP\tito-setup"

# --- Helpers ---
function Write-Step($msg) { Write-Host "`n>> $msg" -ForegroundColor Cyan }
function Test-Command($cmd) { return [bool](Get-Command $cmd -ErrorAction SilentlyContinue) }

# Create temp directory
New-Item -ItemType Directory -Force -Path $TempDir | Out-Null

# --- 1. VC++ Redistributable ---
Write-Step "Installing Visual C++ Redistributable 2015-2022..."
if (-not (Test-Path "HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64")) {
    $vcFile = "$TempDir\vc_redist.x64.exe"
    Invoke-WebRequest -Uri $VcRedist -OutFile $vcFile -UseBasicParsing
    Start-Process -FilePath $vcFile -ArgumentList "/install", "/quiet", "/norestart" -Wait
    Write-Host "  VC++ Redistributable installed." -ForegroundColor Green
} else {
    Write-Host "  VC++ Redistributable already installed." -ForegroundColor Yellow
}

# --- 2. Laragon ---
Write-Step "Installing Laragon 6.0.0..."
if (-not (Test-Path "$LaragonPath\laragon.exe")) {
    $laragonFile = "$TempDir\laragon-wamp.exe"
    Write-Host "  Downloading Laragon 6.0.0..."
    Invoke-WebRequest -Uri $LaragonInstaller -OutFile $laragonFile -UseBasicParsing
    Write-Host "  Running installer (silent)..."
    Start-Process -FilePath $laragonFile -ArgumentList "/VERYSILENT", "/DIR=$LaragonPath", "/SUPPRESSMSGBOXES" -Wait
    Write-Host "  Laragon installed at $LaragonPath" -ForegroundColor Green
} else {
    Write-Host "  Laragon already installed at $LaragonPath" -ForegroundColor Yellow
}

# --- 2b. Laragon packages.conf ---
Write-Step "Configuring Laragon packages.conf..."
$packagesConf = "$LaragonPath\usr\packages.conf"
$customPackages = @"
# PHP
*PHP-8.5=https://windows.php.net/downloads/releases/archives/php-8.5.0-nts-Win32-vs17-x64.zip
*PHP-8.4=https://windows.php.net/downloads/releases/archives/php-8.4.3-nts-Win32-vs17-x64.zip
*PHP-8.3=https://windows.php.net/downloads/releases/archives/php-8.3.16-nts-Win32-vs16-x64.zip
*PHP-8.2=https://windows.php.net/downloads/releases/archives/php-8.2.26-nts-Win32-vs16-x64.zip

# Node.js
node-23.9=https://nodejs.org/dist/v23.9.0/node-v23.9.0-win-x64.zip
node-22.14=https://nodejs.org/dist/v22.14.0/node-v22.14.0-win-x64.zip

# Web Servers
Apache-2.4.63=https://www.apachelounge.com/download/VS17/binaries/httpd-2.4.63-250122-win64-VS17.zip
Nginx-1.27.4=https://nginx.org/download/nginx-1.27.4.zip

# MySQL
mysql-9.1=https://dev.mysql.com/get/Downloads/MySQL-9.1/mysql-9.1.0-winx64.zip
mysql-8.4=https://dev.mysql.com/get/Downloads/MySQL-8.4/mysql-8.4.3-winx64.zip

# PostgreSQL
postgresql-17=https://sbp.enterprisedb.com/getfile.jsp?fileid=1259294
postgresql-16=https://sbp.enterprisedb.com/getfile.jsp?fileid=1259297
"@
if (Test-Path $packagesConf) {
    $existing = Get-Content $packagesConf -Raw
    if ($existing -notlike "*PHP-8.5*") {
        Add-Content -Path $packagesConf -Value "`n$customPackages"
        Write-Host "  Custom packages appended to packages.conf" -ForegroundColor Green
    } else {
        Write-Host "  Custom packages already present." -ForegroundColor Yellow
    }
} else {
    New-Item -ItemType Directory -Force -Path (Split-Path $packagesConf) | Out-Null
    Set-Content -Path $packagesConf -Value $customPackages
    Write-Host "  packages.conf created." -ForegroundColor Green
}

# --- 3. PHP 8.5.6 ---
Write-Step "Setting up PHP 8.5.6 NTS x64..."
if (-not (Test-Path "$PhpPath\php.exe")) {
    $phpFile = "$TempDir\php-8.5.6.zip"
    Write-Host "  Downloading PHP 8.5.6..."
    Invoke-WebRequest -Uri $PhpZip -OutFile $phpFile -UseBasicParsing
    New-Item -ItemType Directory -Force -Path $PhpPath | Out-Null
    Expand-Archive -Path $phpFile -DestinationPath $PhpPath -Force
    # Configure php.ini
    Copy-Item "$PhpPath\php.ini-development" "$PhpPath\php.ini"
    $extensions = @(
        "extension=bcmath", "extension=curl", "extension=fileinfo", "extension=gd",
        "extension=gettext", "extension=intl", "extension=mbstring", "extension=exif",
        "extension=openssl", "extension=pdo_mysql", "extension=pdo_pgsql",
        "extension=pdo_sqlite", "extension=redis", "extension=soap", "extension=sodium",
        "extension=sockets", "extension=xml", "extension=zip", "extension=iconv"
    )
    $iniContent = Get-Content "$PhpPath\php.ini"
    $iniContent = $iniContent -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
    $iniContent += "`n; === Tito AI extensions ==="
    $iniContent += $extensions | ForEach-Object { "`n$_" }
    Set-Content "$PhpPath\php.ini" $iniContent
    Write-Host "  PHP 8.5.6 configured with required extensions." -ForegroundColor Green
} else {
    Write-Host "  PHP 8.5.6 already exists." -ForegroundColor Yellow
}

# --- 4. Update PATH ---
Write-Step "Configuring PATH..."
$pathsToAdd = @($PhpPath, "$LaragonPath\bin\nodejs", "$LaragonPath\bin\git\bin")
$currentPath = [Environment]::GetEnvironmentVariable("Path", "User")
foreach ($p in $pathsToAdd) {
    if ($currentPath -notlike "*$p*") {
        $currentPath = "$p;$currentPath"
        Write-Host "  Added $p to PATH" -ForegroundColor Green
    }
}
[Environment]::SetEnvironmentVariable("Path", $currentPath, "User")
$env:Path = "$($pathsToAdd -join ';');$env:Path"

# --- 5. Composer ---
Write-Step "Installing Composer..."
if (-not (Test-Command "composer")) {
    $composerFile = "$TempDir\Composer-Setup.exe"
    Invoke-WebRequest -Uri $ComposerInstaller -OutFile $composerFile -UseBasicParsing
    Start-Process -FilePath $composerFile -ArgumentList "/VERYSILENT", "/SUPPRESSMSGBOXES", "/PHP=$PhpPath\php.exe" -Wait
    Write-Host "  Composer installed." -ForegroundColor Green
} else {
    Write-Host "  Composer already installed: $(composer --version)" -ForegroundColor Yellow
}

# --- 6. pnpm ---
Write-Step "Installing pnpm..."
if (-not (Test-Command "pnpm")) {
    Invoke-WebRequest -Uri "https://get.pnpm.io/install.ps1" -UseBasicParsing | Invoke-Expression
    Write-Host "  pnpm installed." -ForegroundColor Green
} else {
    Write-Host "  pnpm already installed: $(pnpm --version)" -ForegroundColor Yellow
}

# --- 7. Python (latest via winget) ---
Write-Step "Installing Python (latest)..."
if (-not (Test-Command "python")) {
    winget install --id Python.Python.3.13 --accept-source-agreements --accept-package-agreements --silent
    Write-Host "  Python installed." -ForegroundColor Green
} else {
    Write-Host "  Python already installed: $(python --version)" -ForegroundColor Yellow
}

# --- 8. uv (Python package manager) ---
Write-Step "Installing uv..."
if (-not (Test-Command "uv")) {
    Invoke-WebRequest -Uri "https://astral.sh/uv/install.ps1" -UseBasicParsing | Invoke-Expression
    Write-Host "  uv installed." -ForegroundColor Green
} else {
    Write-Host "  uv already installed: $(uv --version)" -ForegroundColor Yellow
}

# --- 9. Project dependencies ---
Write-Step "Installing project dependencies..."
$projectDir = $PSScriptRoot
if (-not $projectDir) { $projectDir = Get-Location }
Push-Location $projectDir

Write-Host "  Running composer install..."
& "$PhpPath\php.exe" -d memory_limit=-1 (Get-Command composer).Source install --no-interaction

Write-Host "  Running pnpm install..."
pnpm install

# --- 10. Environment setup ---
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    & "$PhpPath\php.exe" artisan key:generate
    Write-Host "  .env created and app key generated." -ForegroundColor Green
} else {
    Write-Host "  .env already exists." -ForegroundColor Yellow
}

# --- 11. Database migrations ---
Write-Step "Running migrations and seeders..."
& "$PhpPath\php.exe" artisan migrate --seed --force
Write-Host "  Migrations and seeders executed." -ForegroundColor Green

Pop-Location

# --- Cleanup ---
Remove-Item -Recurse -Force $TempDir -ErrorAction SilentlyContinue

# --- Done ---
Write-Host "`n========================================" -ForegroundColor Green
Write-Host " Setup complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host @"

Installed:
  - Laragon 6.0.0 (includes Apache, MySQL, Node.js, Git)
  - PHP 8.5.6 NTS x64 (with extensions for Laravel)
  - Composer
  - pnpm
  - Python 3.13
  - uv (Python package manager)

Next steps:
  1. Open a NEW terminal (to pick up PATH changes)
  2. Start Laragon: $LaragonPath\laragon.exe
  3. Run the dev server: composer dev:win

"@ -ForegroundColor White
