#!/bin/bash
# ==============================================================================
# PHP Birthday - Environment Auditor (Linux)
# Validates that the Debian/Ubuntu/Fedora host machine meets all requirements.
# IMPORTANT: It explicitly loads the local .php-env/php.ini to audit the actual
# runtime environment used by the project.
# ==============================================================================

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

ALL_PASSED=true
# Dynamically resolves the script's directory
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

echo -e "${CYAN}=========================================================${NC}"
echo -e "${CYAN} PHP Birthday - Environment Audit (Linux)                ${NC}"
echo -e "${CYAN}=========================================================${NC}"

fail() {
    echo -e "${RED}[FAIL] $1${NC}"
    echo -e "${YELLOW}       -> HINT: $2${NC}\n"
    ALL_PASSED=false
}

pass() {
    echo -e "${GREEN}[PASS] $1${NC}"
}

# 1. Check Global PHP
if ! command -v php &> /dev/null; then
    fail "PHP is not installed or not in PATH." "Run 'sudo apt update && sudo apt install php-cli'."
    PHP_CMD="false"
else
    # 2. Check Local INI (Assuming script is in project root)
    ENV_DIR="$DIR/../.php-env"
    INI_FILE="$ENV_DIR/php.ini"

    if [ ! -d "$ENV_DIR" ]; then
        fail "Local directory '.php-env' is missing." "Create a directory named '.php-env' at the project root."
        PHP_CMD="php"
    elif [ ! -f "$INI_FILE" ]; then
        fail "Local 'php.ini' is missing." "Create a 'php.ini' file inside '.php-env/' to isolate your development settings."
        PHP_CMD="php"
    else
        pass "Local config: .php-env/php.ini exists."
        # Instruct PHP to use the local configuration for all subsequent checks!
        PHP_CMD="php -c $INI_FILE"
    fi

    # 3. Check Version & Xdebug (Using the isolated INI)
    # Note: 2>/dev/null suppresses Xdebug connection timeout warnings.
    if $PHP_CMD -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" 2>/dev/null; then
        PHP_VER=$($PHP_CMD -r "echo PHP_VERSION;" 2>/dev/null)
        pass "PHP Engine: Found version $PHP_VER"
    else
        PHP_VER=$($PHP_CMD -r "echo PHP_VERSION;" 2>/dev/null)
        fail "PHP version is too old ($PHP_VER)." "PHP 8.4.0 or higher is required."
    fi

    if $PHP_CMD -m 2>/dev/null | grep -i -q "xdebug"; then
        pass "PHP Extension: Xdebug is loaded."
    else
        fail "Xdebug extension is not loaded." "Run 'sudo apt install php-xdebug' OR ensure it's enabled in your .php-env/php.ini."
    fi

    # 4. Check actual runtime INI values evaluated by PHP
    if [ -f "$INI_FILE" ]; then
        if $PHP_CMD -r "exit(ini_get('display_errors') == '1' ? 0 : 1);" 2>/dev/null; then
            pass "Runtime INI: 'display_errors' is correctly active."
        else
            fail "INI directive 'display_errors' is not active." "Add 'display_errors = On' to your local php.ini."
        fi

        if $PHP_CMD -r "exit(ini_get('memory_limit') == '-1' ? 0 : 1);" 2>/dev/null; then
            pass "Runtime INI: 'memory_limit' is correctly active."
        else
            fail "INI directive 'memory_limit' is not -1." "Add 'memory_limit = -1' to your local php.ini."
        fi

        if $PHP_CMD -r "exit(strpos(ini_get('xdebug.mode'), 'coverage') !== false ? 0 : 1);" 2>/dev/null; then
            pass "Runtime INI: 'xdebug.mode' allows coverage."
        else
            fail "INI directive 'xdebug.mode' lacks 'coverage'." "Ensure your local php.ini includes 'coverage' in xdebug.mode."
        fi
    fi
fi

# 5. Check Composer
if command -v composer &> /dev/null; then
    pass "Composer: Found globally installed."
elif [ -f "$ENV_DIR/composer.phar" ]; then
    pass "Composer: Found locally in .php-env/."
else
    fail "Composer is missing." "Run 'sudo apt install composer' OR download it manually into .php-env/ from getcomposer.org."
fi

echo -e "${CYAN}=========================================================${NC}"
if [ "$ALL_PASSED" = true ]; then
    echo -e "${GREEN} SUCCESS: Your Linux environment is ready for testing! ${NC}"
else
    echo -e "${RED} WARNING: Please fix the errors above before continuing. ${NC}"
    exit 1
fi
echo -e "${CYAN}=========================================================${NC}"
