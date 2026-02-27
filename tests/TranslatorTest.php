<?php
declare(strict_types=1);

namespace PhpBirthday\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpBirthday\Translator;

/**
 * Unit Tests for the lightweight Translator Engine (i18n).
 * * Verifies that JSON translation files are correctly parsed into memory, 
 * handles missing translation keys safely, and processes dynamic variable substitution.
 */
#[CoversClass(Translator::class)]
class TranslatorTest extends TestCase
{
    private string $tempLocaleDir;

    /**
     * We create a virtual, temporary locales directory containing a mocked 'es.json' (Spanish) file.
     * This isolates the test from the actual 'fr.json'/'en.json' files, ensuring that future 
     * UI text changes do not break the core engine tests.
     */
    protected function setUp(): void
    {
        $this->tempLocaleDir = sys_get_temp_dir() . '/locales/';
        if (!is_dir($this->tempLocaleDir)) {
            mkdir($this->tempLocaleDir);
        }

        // Generate a fake Spanish translation file.
        $dummyLang = [
            'language_name' => 'Español',
            'hello' => 'Hola',
            'event_summary_age' => '%s tiene %d años'
        ];
        
        file_put_contents($this->tempLocaleDir . 'es.json', json_encode($dummyLang));
    }

    /**
     * Cleans up the temporary directory and all files inside it to prevent pollution.
     */
    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->tempLocaleDir}*.*"));
        rmdir($this->tempLocaleDir);
    }

    /**
     * Tests basic Key-to-Value resolution.
     */
    public function testLoadsExactLanguageAndTranslates(): void
    {
        // ACT: Initialize pointing to 'es'
        $translator = new Translator($this->tempLocaleDir, 'es');
        
        // ASSERT
        $this->assertEquals('Hola', $translator->get('hello'), 'Should return the exact mapped value.');
    }

    /**
     * Tests the safety net: if a developer requests a key that does not exist in the JSON,
     * the engine should not crash, but rather return the raw key name to aid debugging.
     */
    public function testReturnsRawKeyIfTranslationIsMissing(): void
    {
        $translator = new Translator($this->tempLocaleDir, 'es');
        $this->assertEquals(
            'missing_key_example', 
            $translator->get('missing_key_example'),
            'Missing keys should return themselves as a fallback.'
        );
    }

    /**
     * Tests dynamic string injection using the variadic arguments (...$args) and sprintf.
     */
    public function testVariableSubstitutionWithSprintf(): void
    {
        $translator = new Translator($this->tempLocaleDir, 'es');
        
        // ACT: Provide two arguments to fulfill the '%s' (string) and '%d' (digit) placeholders.
        $result = $translator->get('event_summary_age', 'Martin', 46);
        
        // ASSERT
        $this->assertEquals(
            'Martin tiene 46 años', 
            $result,
            'Variadic arguments should be correctly injected into the localized string.'
        );
    }

    /**
     * Tests the static method used by the UI to populate the dropdown menu of available languages.
     */
    public function testGetAvailableLanguagesScansDirectoryCorrectly(): void
    {
        // ACT: Scan our temporary directory
        $map = Translator::getAvailableLanguages($this->tempLocaleDir);
        
        // ASSERT: It should find 'es.json' and extract the 'language_name' property inside it.
        $this->assertArrayHasKey('es', $map, 'The map should contain the file basename as the key.');
        $this->assertEquals('Español', $map['es'], 'The map value should be the native language_name property.');
    }
}
