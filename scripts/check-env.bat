@echo off
setlocal EnableDelayedExpansion

:: ==============================================================================
:: PHP Birthday - Environment Auditor (Windows Pure Batch)
:: 
:: Validates that the Windows host machine meets all requirements (PHP 8.4+, 
:: Xdebug, Composer) to develop and test the application.
:: IMPORTANT: It explicitly loads the local .php-env\php.ini to audit the actual
:: runtime environment used by the project launchers.
:: ==============================================================================

set "ALL_PASSED=true"
set "ENV_DIR=%~dp0..\.php-env"
set "INI_FILE=%ENV_DIR%\php.ini"

echo =========================================================
echo  PHP Birthday - Environment Audit (Windows Native)
echo =========================================================
echo.

:: ------------------------------------------------------------------------------
:: 1. Check Global PHP Availability
:: ------------------------------------------------------------------------------
where php >nul 2>nul
if !ERRORLEVEL! NEQ 0 (
    call :Fail "PHP is not installed or not in the system PATH." "Download PHP NTS from https://windows.php.net/download/ and add the extracted folder to your Windows Environment Variables (PATH)."
    goto :check_composer
)

:: ------------------------------------------------------------------------------
:: 2. Check Local Environment Configuration
:: ------------------------------------------------------------------------------
if not exist "%INI_FILE%" (
    call :Fail "Local 'php.ini' is missing." "Create a 'php.ini' file inside '.php-env\' so the auditor can verify your local development settings."
    set "PHP_CMD=php"
) else (
    call :Pass "Local config: .php-env\php.ini exists."
    set "PHP_CMD=php -c "%INI_FILE%""
)

:: ------------------------------------------------------------------------------
:: 3. Evaluate PHP & Extensions (Using the isolated INI if present)
:: Note: '2>nul' is used to suppress Xdebug connection timeout warnings.
:: ------------------------------------------------------------------------------
%PHP_CMD% -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" 2>nul
if !ERRORLEVEL! NEQ 0 (
    for /f "delims=" %%v in ('%PHP_CMD% -r "echo PHP_VERSION;" 2^>nul') do set "PHP_VER=%%v"
    call :Fail "PHP version is too old (!PHP_VER!)." "PHP 8.4.0 or higher is required."
) else (
    for /f "delims=" %%v in ('%PHP_CMD% -r "echo PHP_VERSION;" 2^>nul') do set "PHP_VER=%%v"
    call :Pass "PHP Engine: Found version !PHP_VER!"
)

%PHP_CMD% -m 2>nul | findstr /i "xdebug" >nul
if !ERRORLEVEL! NEQ 0 (
    call :Fail "Xdebug extension is not loaded." "Ensure php_xdebug.dll is in your ext/ folder and enabled in .php-env\php.ini."
) else (
    call :Pass "PHP Extension: Xdebug is loaded."
)

:: ------------------------------------------------------------------------------
:: 4. Validate INI Parameters dynamically via PHP's memory (Golden Master approach)
:: Using str_contains instead of '!==' to avoid Batch Delayed Expansion conflicts.
:: ------------------------------------------------------------------------------
if exist "%INI_FILE%" (
    %PHP_CMD% -r "exit(ini_get('display_errors') == '1' ? 0 : 1);" 2>nul
    if !ERRORLEVEL! NEQ 0 (
        call :Fail "INI directive 'display_errors' is not On." "Add 'display_errors = On' to your .php-env\php.ini."
    ) else (
        call :Pass "Runtime INI: 'display_errors' is correctly active."
    )

    %PHP_CMD% -r "exit(ini_get('memory_limit') == '-1' ? 0 : 1);" 2>nul
    if !ERRORLEVEL! NEQ 0 (
        call :Fail "INI directive 'memory_limit' is not -1." "Add 'memory_limit = -1' to your .php-env\php.ini."
    ) else (
        call :Pass "Runtime INI: 'memory_limit' is correctly active."
    )

    %PHP_CMD% -r "exit(str_contains(ini_get('xdebug.mode'), 'coverage') ? 0 : 1);" 2>nul
    if !ERRORLEVEL! NEQ 0 (
        call :Fail "INI directive 'xdebug.mode' lacks 'coverage'." "Ensure your .php-env\php.ini includes 'coverage' in xdebug.mode."
    ) else (
        call :Pass "Runtime INI: 'xdebug.mode' allows coverage."
    )
)

:: ------------------------------------------------------------------------------
:: 5. Check Composer
:: ------------------------------------------------------------------------------
:check_composer
where composer >nul 2>nul
if !ERRORLEVEL! EQU 0 (
    call :Pass "Composer: Found globally installed."
) else (
    if exist "%ENV_DIR%\composer.phar" (
        call :Pass "Composer: Found locally in .php-env\."
    ) else (
        call :Fail "Composer is missing." "Download the Windows Installer from getcomposer.org OR place 'composer.phar' in the .php-env\ directory."
    )
)

:: ------------------------------------------------------------------------------
:: Footer
:: ------------------------------------------------------------------------------
echo.
echo =========================================================
if "%ALL_PASSED%"=="true" (
    echo  SUCCESS: Your Windows environment is ready for testing!
) else (
    echo  WARNING: Please fix the errors above before continuing.
)
echo =========================================================
echo.
pause
goto :eof

:: ==============================================================================
:: Reusable Functions (Subroutines)
:: ==============================================================================
:Fail
echo [FAIL] %~1
echo        -^> HINT: %~2
echo.
set "ALL_PASSED=false"
exit /b

:Pass
echo [PASS] %~1
exit /b
