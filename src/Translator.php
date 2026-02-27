<?php
declare(strict_types=1);

namespace PhpBirthday;

/**
 * Lightweight JSON-based i18n Translation Engine.
 * * Instantiated with the desired language to avoid global state.
 * Allows seamless mocking in unit tests by avoiding static properties for translation fetching.
 */
class Translator
{
    /** @var array<string, string> Key-value pairs of loaded translations */
    private array $messages = [];

    /**
     * Loads the specific locale file into memory.
     * * @param string $localesDir Path to the locales directory.
     * @param string $lang The desired language code (e.g., 'en', 'fr').
     */
    public function __construct(string $localesDir, string $lang)
    {
        $file = $localesDir . strtolower($lang) . '.json';

        // Fallback to English if the requested locale file is missing
        if (!file_exists($file)) {
            $file = $localesDir . Constants::DEFAULT_LANG . '.json';
        }

        if (file_exists($file)) {
            $json = file_get_contents($file);
            $decoded = json_decode((string)$json, true);
            if (is_array($decoded)) {
                $this->messages = $decoded;
            }
        }
    }

    /**
     * Retrieves a translated string and optionally formats it with variables.
     * * @param string $key The translation key.
     * @param mixed ...$args Optional arguments for sprintf replacement.
     * @return string The translated and formatted string.
     */
    public function get(string $key, ...$args): string
    {
        // Return the raw key if the translation doesn't exist to aid debugging
        $text = $this->messages[$key] ?? $key;
        return !empty($args) ? sprintf($text, ...$args) : $text;
    }

    /**
     * Scans the locales directory and returns an associative array of all available languages.
     * * @param string $localesDir Path to the locales directory.
     * @return array<string, string> Map of language codes to their native display names.
     */
    public static function getAvailableLanguages(string $localesDir): array
    {
        $languages = [];
        if (is_dir($localesDir)) {
            // Find all JSON files in the locales directory
            foreach (glob($localesDir . '*.json') as $file) {
                $code = basename($file, '.json');
                $content = json_decode((string)file_get_contents($file), true);
                
                // Extract the native language name from the JSON, or fallback to the uppercase code
                $name = $content[Constants::I18N_LANG_NAME] ?? strtoupper($code);
                $languages[$code] = $name;
            }
        }
        return $languages;
    }
}
