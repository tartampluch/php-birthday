<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 1. PSR-4 Compliant Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'PhpBirthday\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) { require $file; }
});

use PhpBirthday\ConfigManager;
use PhpBirthday\Translator;
use PhpBirthday\AuthManager;
use PhpBirthday\Application;
use PhpBirthday\Constants;

// 2. Initialize Core Dependencies
$configManager = new ConfigManager(Constants::PATH_CONFIG_FILE);

// Prevent access to uninitialized application instances
if (!$configManager->isInitialized()) {
    header(Constants::HEADER_503_STATUS);
    header(Constants::HEADER_PLAIN_TYPE);
    
    // Fallback translation load since app isn't configured yet
    $translator = new Translator(Constants::PATH_LOCALES_DIR, Constants::DEFAULT_LANG);
    echo $translator->get('ui_not_initialized');
    exit;
}

$config = $configManager->load();
$translator = new Translator(Constants::PATH_LOCALES_DIR, $config[Constants::CONFIG_LANG]);

// 3. Application Security Verification
$authManager = new AuthManager();
if (!$authManager->isAuthenticated($config, $_SERVER)) {
    $authManager->sendUnauthorizedHeaders($translator);
}

// 4. Instantiate and Run the Application Engine
$app = new Application($configManager, $translator);
$app->run($_SERVER);
