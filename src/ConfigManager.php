<?php
declare(strict_types=1);

namespace PhpBirthday;

/**
 * Configuration Manager
 * * Handles reading and writing application settings to a persistent JSON file.
 * Built as an instantiable object to allow Dependency Injection during tests
 * (e.g., passing a mock file path or a virtual stream).
 */
class ConfigManager
{
    private string $filePath;

    /**
     * Initializes the manager with a specific configuration file path.
     * * @param string $filePath Absolute path to the config.json file.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Loads configuration from the JSON file.
     * Injects defaults for any missing key to ensure stability and backwards compatibility.
     * * @return array The complete configuration map.
     */
    public function load(): array
    {
        $defaults = [
            Constants::CONFIG_LANG => Constants::DEFAULT_LANG,
            Constants::CONFIG_MODE => Constants::MODE_URL,
            Constants::CONFIG_URL  => '',
            Constants::CONFIG_USER => '',
            Constants::CONFIG_PASS => '',
            Constants::CONFIG_REFRESH_INTERVAL => Constants::DEFAULT_REFRESH_MINUTES,
            
            // App Security
            Constants::CONFIG_APP_USER => '',
            Constants::CONFIG_APP_PASS_HASH => '',
            
            // Reminder Defaults
            Constants::CONFIG_REMINDER_ENABLED => false,
            Constants::CONFIG_REMINDER_VALUE => 1,
            Constants::CONFIG_REMINDER_UNIT => Constants::UNIT_DAYS,
            Constants::CONFIG_REMINDER_DIR => Constants::DIR_BEFORE,
        ];

        if (!file_exists($this->filePath)) {
            return $defaults;
        }

        $json = file_get_contents($this->filePath);
        $data = json_decode((string)$json, true);

        return is_array($data) ? array_merge($defaults, $data) : $defaults;
    }

    /**
     * Persists the configuration array to the JSON file securely.
     * Creates the target directory if it does not exist.
     * * @param array $config The configuration map to save.
     */
    public function save(array $config): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, Constants::DIR_PERMISSIONS, true);
        }

        file_put_contents($this->filePath, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Verifies if the application setup process has been completed
     * (i.e., an admin user and password hash are set).
     * * @return bool True if initialized, false otherwise.
     */
    public function isInitialized(): bool
    {
        $config = $this->load();
        return !empty($config[Constants::CONFIG_APP_USER]) && !empty($config[Constants::CONFIG_APP_PASS_HASH]);
    }
}
