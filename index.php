<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// PSR-4 Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'PhpBirthday\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) { require $file; }
});

use PhpBirthday\ConfigManager;
use PhpBirthday\AuthManager;
use PhpBirthday\Constants;
use PhpBirthday\Translator;

$configManager = new ConfigManager(Constants::PATH_CONFIG_FILE);
$isInitialized = $configManager->isInitialized();
$config = $configManager->load();
$successMessage = '';

// Load Translator (Defaults to English if setup hasn't occurred)
$translator = new Translator(Constants::PATH_LOCALES_DIR, $config[Constants::CONFIG_LANG]);

// 1. Handle Setup Pipeline (First Run Scenario)
if (!$isInitialized && $_SERVER['REQUEST_METHOD'] === Constants::HTTP_METHOD_POST && isset($_POST[Constants::FORM_SETUP_USER], $_POST[Constants::FORM_SETUP_PASS])) {
    $config[Constants::CONFIG_APP_USER] = trim($_POST[Constants::FORM_SETUP_USER]);
    $config[Constants::CONFIG_APP_PASS_HASH] = password_hash($_POST[Constants::FORM_SETUP_PASS], PASSWORD_DEFAULT);
    $configManager->save($config);
    header("Location: index.php");
    exit;
}

// 2. Secure UI via Basic Auth for initialized instances
if ($isInitialized) {
    $authManager = new AuthManager();
    if (!$authManager->isAuthenticated($config, $_SERVER)) {
        $authManager->sendUnauthorizedHeaders($translator);
    }
}

// 3. Process Settings Form Submission or Actions
if ($isInitialized && $_SERVER['REQUEST_METHOD'] === Constants::HTTP_METHOD_POST && isset($_POST[Constants::FORM_ACTION])) {
    $action = $_POST[Constants::FORM_ACTION];

    // Handle the "Refresh" cache bypass action
    if ($action === Constants::ACTION_REFRESH) {
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        $successMessage = $translator->get('notif_sync_success');
    } 
    // Handle standard save settings action
    elseif ($action === Constants::ACTION_SAVE && isset($_POST[Constants::CONFIG_MODE])) {
        $newConfig = $config; // Retain system credentials
        
        $newConfig[Constants::CONFIG_LANG] = $_POST[Constants::CONFIG_LANG] ?? Constants::DEFAULT_LANG;
        $newConfig[Constants::CONFIG_MODE] = $_POST[Constants::CONFIG_MODE] ?? Constants::MODE_URL;
        $newConfig[Constants::CONFIG_URL]  = $_POST[Constants::CONFIG_URL] ?? '';
        $newConfig[Constants::CONFIG_USER] = $_POST[Constants::CONFIG_USER] ?? '';
        $newConfig[Constants::CONFIG_PASS] = $_POST[Constants::CONFIG_PASS] ?? '';
        
        $newConfig[Constants::CONFIG_REFRESH_INTERVAL] = (int)($_POST[Constants::CONFIG_REFRESH_INTERVAL] ?? Constants::DEFAULT_REFRESH_MINUTES);

        $newConfig[Constants::CONFIG_REMINDER_ENABLED] = isset($_POST[Constants::CONFIG_REMINDER_ENABLED]);
        $newConfig[Constants::CONFIG_REMINDER_VALUE]   = (int)($_POST[Constants::CONFIG_REMINDER_VALUE] ?? 1);
        $newConfig[Constants::CONFIG_REMINDER_UNIT]    = $_POST[Constants::CONFIG_REMINDER_UNIT] ?? Constants::UNIT_DAYS;
        $newConfig[Constants::CONFIG_REMINDER_DIR]     = $_POST[Constants::CONFIG_REMINDER_DIR] ?? Constants::DIR_BEFORE;

        // Handle Local VCF File Uploads
        if ($newConfig[Constants::CONFIG_MODE] === Constants::MODE_LOCAL && isset($_FILES[Constants::FORM_FILE_UPLOAD]) && $_FILES[Constants::FORM_FILE_UPLOAD]['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(Constants::PATH_DATA_DIR)) {
                mkdir(Constants::PATH_DATA_DIR, Constants::DIR_PERMISSIONS, true);
            }
            move_uploaded_file($_FILES[Constants::FORM_FILE_UPLOAD]['tmp_name'], Constants::PATH_UPLOADED_VCF);
        }

        $configManager->save($newConfig);
        
        // Refresh dependencies state with new language/settings
        $config = clone (object)$newConfig;
        $config = (array)$config;
        $translator = new Translator(Constants::PATH_LOCALES_DIR, $config[Constants::CONFIG_LANG]);
        
        // Flush APCu cache to apply changes immediately
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        
        $successMessage = $translator->get('ui_saved');
    }
}

// 4. Generate Output URL robustly (handling standard ports and Windows pathing)
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$protocol = $isHttps ? Constants::PROTOCOL_HTTPS : Constants::PROTOCOL_HTTP;
$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
$port = isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : ($isHttps ? 443 : 80);

$portSuffix = '';
if (($isHttps && $port !== 443) || (!$isHttps && $port !== 80)) {
    if (!str_contains($host, ':')) {
        $portSuffix = ':' . $port;
    }
}

$dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$dir = rtrim($dir, '/');

$feedUrl = '';
if ($isInitialized) {
    $feedUrl = $protocol . $host . $portSuffix . $dir . '/feed.php';
}

$availableLanguages = Translator::getAvailableLanguages(Constants::PATH_LOCALES_DIR);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($config[Constants::CONFIG_LANG]); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Constants::APP_NAME; ?> - Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl overflow-hidden my-8">
        <div class="bg-blue-600 px-6 py-4">
            <h1 class="text-2xl font-bold text-white"><?php echo Constants::APP_NAME; ?></h1>
            <p class="text-blue-100 text-sm"><?php echo $translator->get('ui_subtitle'); ?></p>
        </div>

        <div class="p-6">
            <?php if (!$isInitialized): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $translator->get('ui_setup_title'); ?></h2>
                    <p class="text-gray-600 mb-6"><?php echo $translator->get('ui_setup_desc'); ?></p>
                    
                    <form action="index.php" method="POST" class="space-y-4">
                        <div>
                            <label for="<?php echo Constants::FORM_SETUP_USER; ?>" class="block text-sm font-medium text-gray-700"><?php echo $translator->get('ui_setup_user'); ?></label>
                            <input type="text" name="<?php echo Constants::FORM_SETUP_USER; ?>" id="<?php echo Constants::FORM_SETUP_USER; ?>" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="<?php echo Constants::FORM_SETUP_PASS; ?>" class="block text-sm font-medium text-gray-700"><?php echo $translator->get('ui_setup_pass'); ?></label>
                            <input type="password" name="<?php echo Constants::FORM_SETUP_PASS; ?>" id="<?php echo Constants::FORM_SETUP_PASS; ?>" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                        </div>
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <?php echo $translator->get('ui_setup_btn'); ?>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <?php if ($successMessage): ?>
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo $successMessage; ?></span>
                    </div>
                <?php endif; ?>

                <div class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <label class="block text-sm font-bold text-gray-700 mb-1"><?php echo $translator->get('ui_feed_url'); ?></label>
                    <div class="flex">
                        <input type="text" readonly value="<?php echo htmlspecialchars($feedUrl); ?>" class="flex-1 block w-full rounded-l-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 border focus:outline-none" onclick="this.select();">
                        <a href="feed.php" target="_blank" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                            <?php echo $translator->get('ui_test_feed'); ?>
                        </a>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><?php echo $translator->get('ui_feed_help'); ?></p>
                </div>

                <form action="index.php" method="POST" enctype="multipart/form-data" class="space-y-8">
                    
                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4"><?php echo $translator->get('lbl_source'); ?></h3>
                        <div class="mb-4">
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center">
                                    <input type="radio" name="<?php echo Constants::CONFIG_MODE; ?>" value="<?php echo Constants::MODE_URL; ?>" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" <?php echo $config[Constants::CONFIG_MODE] === Constants::MODE_URL ? 'checked' : ''; ?> onchange="toggleMode()">
                                    <span class="ml-2 text-sm text-gray-700"><?php echo $translator->get('mode_carddav'); ?></span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="<?php echo Constants::CONFIG_MODE; ?>" value="<?php echo Constants::MODE_LOCAL; ?>" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" <?php echo $config[Constants::CONFIG_MODE] === Constants::MODE_LOCAL ? 'checked' : ''; ?> onchange="toggleMode()">
                                    <span class="ml-2 text-sm text-gray-700"><?php echo $translator->get('mode_local'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div id="block_url" class="space-y-4 <?php echo $config[Constants::CONFIG_MODE] === Constants::MODE_LOCAL ? 'hidden' : ''; ?>">
                            <div>
                                <label for="<?php echo Constants::CONFIG_URL; ?>" class="block text-sm font-bold text-gray-700"><?php echo $translator->get('lbl_url'); ?></label>
                                <input type="url" name="<?php echo Constants::CONFIG_URL; ?>" id="<?php echo Constants::CONFIG_URL; ?>" value="<?php echo htmlspecialchars($config[Constants::CONFIG_URL]); ?>" placeholder="https://..." class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500"><?php echo $translator->get('help_carddav_url'); ?></p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="<?php echo Constants::CONFIG_USER; ?>" class="block text-sm font-bold text-gray-700"><?php echo $translator->get('lbl_user'); ?></label>
                                    <input type="text" name="<?php echo Constants::CONFIG_USER; ?>" id="<?php echo Constants::CONFIG_USER; ?>" value="<?php echo htmlspecialchars($config[Constants::CONFIG_USER]); ?>" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="<?php echo Constants::CONFIG_PASS; ?>" class="block text-sm font-bold text-gray-700"><?php echo $translator->get('lbl_pass'); ?></label>
                                    <input type="password" name="<?php echo Constants::CONFIG_PASS; ?>" id="<?php echo Constants::CONFIG_PASS; ?>" value="<?php echo htmlspecialchars($config[Constants::CONFIG_PASS]); ?>" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>
                        </div>

                        <div id="block_local" class="space-y-4 <?php echo $config[Constants::CONFIG_MODE] === Constants::MODE_URL ? 'hidden' : ''; ?>">
                            <div>
                                <label for="<?php echo Constants::FORM_FILE_UPLOAD; ?>" class="sr-only"><?php echo $translator->get('btn_browse'); ?></label>
                                <input type="file" name="<?php echo Constants::FORM_FILE_UPLOAD; ?>" id="<?php echo Constants::FORM_FILE_UPLOAD; ?>" accept=".vcf,.vcard" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <?php if (file_exists(Constants::PATH_UPLOADED_VCF)): ?>
                                    <p class="mt-2 text-sm text-green-600">✓ <?php echo $translator->get('ui_file_present'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4"><?php echo $translator->get('lbl_general'); ?></h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr] gap-x-6 gap-y-6">
                            
                            <div class="sm:pt-2">
                                <label for="<?php echo Constants::CONFIG_LANG; ?>" class="block text-sm font-bold text-gray-700 whitespace-nowrap"><?php echo $translator->get('lbl_language'); ?></label>
                            </div>
                            <div class="w-full">
                                <select id="<?php echo Constants::CONFIG_LANG; ?>" name="<?php echo Constants::CONFIG_LANG; ?>" class="block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                    <?php foreach ($availableLanguages as $code => $name): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $config[Constants::CONFIG_LANG] === $code ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500"><?php echo $translator->get('help_language'); ?></p>
                            </div>

                            <div class="sm:pt-2">
                                <label for="<?php echo Constants::CONFIG_REFRESH_INTERVAL; ?>" class="block text-sm font-bold text-gray-700 whitespace-nowrap"><?php echo $translator->get('lbl_refresh_interval'); ?></label>
                            </div>
                            <div class="w-full">
                                <div class="flex items-center space-x-3 w-full">
                                    <input type="number" min="0" name="<?php echo Constants::CONFIG_REFRESH_INTERVAL; ?>" id="<?php echo Constants::CONFIG_REFRESH_INTERVAL; ?>" value="<?php echo (int)$config[Constants::CONFIG_REFRESH_INTERVAL]; ?>" class="flex-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                    <span class="text-sm text-gray-700 whitespace-nowrap"><?php echo $translator->get('lbl_minutes_suffix'); ?></span>
                                    
                                    <button type="submit" name="<?php echo Constants::FORM_ACTION; ?>" value="<?php echo Constants::ACTION_REFRESH; ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="-ml-0.5 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <?php echo $translator->get('menu_refresh'); ?>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500"><?php echo $translator->get('help_interval'); ?></p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2"><?php echo $translator->get('lbl_notifications'); ?></h3>
                        <div class="flex items-center mb-4">
                            <input type="checkbox" id="<?php echo Constants::CONFIG_REMINDER_ENABLED; ?>" name="<?php echo Constants::CONFIG_REMINDER_ENABLED; ?>" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo $config[Constants::CONFIG_REMINDER_ENABLED] ? 'checked' : ''; ?> onchange="toggleReminders()">
                            <label for="<?php echo Constants::CONFIG_REMINDER_ENABLED; ?>" class="ml-2 block text-sm font-medium text-gray-900">
                                <?php echo $translator->get('lbl_enable_reminders'); ?>
                            </label>
                        </div>

                        <div id="block_reminders" class="flex flex-col sm:flex-row sm:items-center gap-3 <?php echo $config[Constants::CONFIG_REMINDER_ENABLED] ? '' : 'opacity-50 pointer-events-none'; ?>">
                            <input type="number" min="0" name="<?php echo Constants::CONFIG_REMINDER_VALUE; ?>" id="<?php echo Constants::CONFIG_REMINDER_VALUE; ?>" value="<?php echo (int)$config[Constants::CONFIG_REMINDER_VALUE]; ?>" class="flex-1 min-w-[60px] block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                            
                            <select name="<?php echo Constants::CONFIG_REMINDER_UNIT; ?>" class="flex-1 min-w-[100px] block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                <option value="<?php echo Constants::UNIT_DAYS; ?>" <?php echo $config[Constants::CONFIG_REMINDER_UNIT] === Constants::UNIT_DAYS ? 'selected' : ''; ?>><?php echo $translator->get('unit_days'); ?></option>
                                <option value="<?php echo Constants::UNIT_HOURS; ?>" <?php echo $config[Constants::CONFIG_REMINDER_UNIT] === Constants::UNIT_HOURS ? 'selected' : ''; ?>><?php echo $translator->get('unit_hours'); ?></option>
                                <option value="<?php echo Constants::UNIT_MINUTES; ?>" <?php echo $config[Constants::CONFIG_REMINDER_UNIT] === Constants::UNIT_MINUTES ? 'selected' : ''; ?>><?php echo $translator->get('unit_minutes'); ?></option>
                            </select>
                            
                            <select name="<?php echo Constants::CONFIG_REMINDER_DIR; ?>" class="flex-1 min-w-[100px] block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm">
                                <option value="<?php echo Constants::DIR_BEFORE; ?>" <?php echo $config[Constants::CONFIG_REMINDER_DIR] === Constants::DIR_BEFORE ? 'selected' : ''; ?>><?php echo $translator->get('dir_before'); ?></option>
                                <option value="<?php echo Constants::DIR_AFTER; ?>" <?php echo $config[Constants::CONFIG_REMINDER_DIR] === Constants::DIR_AFTER ? 'selected' : ''; ?>><?php echo $translator->get('dir_after'); ?></option>
                            </select>
                            
                            <span class="text-sm font-medium text-gray-700 whitespace-nowrap text-right sm:text-left"><?php echo $translator->get('lbl_start_of_day'); ?></span>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end border-t border-gray-200 mt-6">
                        <button type="submit" name="<?php echo Constants::FORM_ACTION; ?>" value="<?php echo Constants::ACTION_SAVE; ?>" class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-6 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 mt-4">
                            <?php echo $translator->get('btn_save'); ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isInitialized): ?>
    <script>
        function toggleMode() {
            const modeName = "<?php echo Constants::CONFIG_MODE; ?>";
            const valUrl = "<?php echo Constants::MODE_URL; ?>";
            const isUrl = document.querySelector(`input[name="${modeName}"][value="${valUrl}"]`).checked;
            document.getElementById('block_url').classList.toggle('hidden', !isUrl);
            document.getElementById('block_local').classList.toggle('hidden', isUrl);
        }

        function toggleReminders() {
            const idEnabled = "<?php echo Constants::CONFIG_REMINDER_ENABLED; ?>";
            const isEnabled = document.getElementById(idEnabled).checked;
            const block = document.getElementById('block_reminders');
            if (isEnabled) {
                block.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                block.classList.add('opacity-50', 'pointer-events-none');
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
