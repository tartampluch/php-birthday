<#
.SYNOPSIS
    Environment Health Check for Windows (Golden Master Standard).

.DESCRIPTION
    Validates that the host machine meets all requirements (PHP 8.4+, Xdebug, 
    Composer). It explicitly loads the local .php-env\php.ini to audit the actual
    runtime environment used by the project.
#>

$ErrorActionPreference = "Continue"

Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " PHP Birthday - Environment Audit (Windows PowerShell)   " -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan

$allPassed = $true

function Write-Fail ($msg, $hint) {
    Write-Host "[FAIL] $msg" -ForegroundColor Red
    Write-Host "       -> HINT: $hint`n" -ForegroundColor Yellow
    $script:allPassed = $false
}

function Write-Pass ($msg) {
    Write-Host "[PASS] $msg" -ForegroundColor Green
}

# 1. Check Global PHP
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpAvailable = $false

if (-Not $phpCommand) {
    Write-Fail "PHP is not installed or not in the system PATH." "Download PHP NTS from https://windows.php.net/download/ and add the extracted folder to your Windows Environment Variables (PATH)."
} else {
    $phpAvailable = $true
}

# 2. Check Local INI (Assuming script is in project root)
$envDir = Join-Path $PSScriptRoot "..\.php-env"
$iniFile = Join-Path $envDir "php.ini"
$hasIni = $false

if (-Not (Test-Path $envDir)) {
    Write-Fail "Local directory '.php-env' is missing." "Create a folder named '.php-env' at the root of the project to store your local testing configuration."
} elseif (-Not (Test-Path $iniFile)) {
    Write-Fail "Local 'php.ini' is missing." "Create a 'php.ini' file inside '.php-env\' so the auditor can verify your local development settings."
} else {
    Write-Pass "Local config: .php-env\php.ini exists."
    $hasIni = $true
}

# 3. Evaluate PHP & Extensions (Using the isolated INI if present)
if ($phpAvailable) {
    $baseArgs = @()
    if ($hasIni) {
        $baseArgs += "-c", $iniFile
    }

    # Version Check (2>$null suppresses Xdebug timeout warnings)
    $verCheckArgs = $baseArgs + @("-r", "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);")
    & php $verCheckArgs 2>$null
    
    if ($LASTEXITCODE -ne 0) {
        $verPrintArgs = $baseArgs + @("-r", "echo PHP_VERSION;")
        $phpVer = & php $verPrintArgs 2>$null
        Write-Fail "PHP version is too old ($phpVer)." "PHP 8.4.0 or higher is required."
    } else {
        $verPrintArgs = $baseArgs + @("-r", "echo PHP_VERSION;")
        $phpVer = & php $verPrintArgs 2>$null
        Write-Pass "PHP Engine: Found version $phpVer"
    }

    # Xdebug Check
    $modArgs = $baseArgs + @("-m")
    $modules = & php $modArgs 2>$null
    if ($modules -match "xdebug") {
        Write-Pass "PHP Extension: Xdebug is loaded."
    } else {
        Write-Fail "Xdebug extension is not loaded." "Ensure php_xdebug.dll is in your ext/ folder and enabled in .php-env\php.ini."
    }

    # 4. Validate INI Parameters dynamically via PHP's memory
    if ($hasIni) {
        $deArgs = $baseArgs + @("-r", "exit(ini_get('display_errors') == '1' ? 0 : 1);")
        & php $deArgs 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Fail "INI directive 'display_errors' is not active." "Add 'display_errors = On' to your local php.ini."
        } else {
            Write-Pass "Runtime INI: 'display_errors' is correctly active."
        }

        $mlArgs = $baseArgs + @("-r", "exit(ini_get('memory_limit') == '-1' ? 0 : 1);")
        & php $mlArgs 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Fail "INI directive 'memory_limit' is not -1." "Add 'memory_limit = -1' to your local php.ini."
        } else {
            Write-Pass "Runtime INI: 'memory_limit' is correctly active."
        }

        $xmArgs = $baseArgs + @("-r", "exit(strpos(ini_get('xdebug.mode'), 'coverage') !== false ? 0 : 1);")
        & php $xmArgs 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Fail "INI directive 'xdebug.mode' lacks 'coverage'." "Ensure your local php.ini includes 'coverage' in xdebug.mode."
        } else {
            Write-Pass "Runtime INI: 'xdebug.mode' allows coverage."
        }
    }
}

# 5. Check Composer
$composerGlobal = Get-Command composer -ErrorAction SilentlyContinue
$composerLocal = Test-Path "$envDir\composer.phar"

if ($composerGlobal) {
    Write-Pass "Composer: Found globally installed."
} elseif ($composerLocal) {
    Write-Pass "Composer: Found locally in .php-env\."
} else {
    Write-Fail "Composer is missing." "Download the Windows Installer from getcomposer.org OR place 'composer.phar' in the .php-env\ directory."
}

# Footer
Write-Host "`n=========================================================" -ForegroundColor Cyan
if ($allPassed) {
    Write-Host " SUCCESS: Your Windows environment is ready for testing! " -ForegroundColor Green
} else {
    Write-Host " WARNING: Please fix the errors above before continuing. " -ForegroundColor Red
}
Write-Host "=========================================================" -ForegroundColor Cyan
