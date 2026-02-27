<?php
declare(strict_types=1);

namespace PhpBirthday\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpBirthday\ConfigManager;
use PhpBirthday\Constants;

/**
 * Unit Tests for the ConfigManager.
 * * Ensures that application settings are reliably saved to and read from the disk (JSON).
 * It also verifies the initialization state (Setup phase).
 */
#[CoversClass(ConfigManager::class)]
class ConfigManagerTest extends TestCase
{
    private string $tempFilePath;
    private ConfigManager $manager;

    /**
     * To prevent our tests from destroying the actual configuration file used 
     * by the developer or the production environment, we instruct the ConfigManager 
     * to point to a temporary file located in the OS Temp directory.
     */
    protected function setUp(): void
    {
        $this->tempFilePath = sys_get_temp_dir() . '/php_birthday_test_config.json';
        
        // Ensure the file doesn't exist from a previously crashed test run.
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
        
        $this->manager = new ConfigManager($this->tempFilePath);
    }

    /**
     * tearDown() runs AFTER each test. We use it to wipe our temporary files, 
     * keeping the testing environment pristine (Leave no trace).
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }

    /**
     * Tests the scenario where the application runs for the very first time.
     */
    public function testLoadsDefaultsWhenFileDoesNotExist(): void
    {
        // ACT: Load config from a file path that doesn't exist.
        $config = $this->manager->load();
        
        // ASSERT: The manager should fallback to hardcoded default values.
        $this->assertEquals(Constants::DEFAULT_LANG, $config[Constants::CONFIG_LANG]);
        
        // ASSERT: Without saved user credentials, the app must report it is NOT initialized.
        $this->assertFalse($this->manager->isInitialized(), 'A fresh installation should not be initialized.');
    }

    /**
     * Tests the full read/write cycle to the filesystem.
     */
    public function testSavesAndLoadsConfigurationCorrectly(): void
    {
        // ARRANGE: Get the base defaults and modify them as a user would via the UI form.
        $config = $this->manager->load();
        $config[Constants::CONFIG_APP_USER] = 'martin';
        $config[Constants::CONFIG_APP_PASS_HASH] = 'hashed_pass';
        $config[Constants::CONFIG_REMINDER_VALUE] = 5;

        // ACT: Save to disk, then immediately force a reload from disk into a new variable.
        $this->manager->save($config);
        $reloadedConfig = $this->manager->load();

        // ASSERT: Verify filesystem persistence.
        $this->assertTrue(file_exists($this->tempFilePath), 'The JSON file must be physically created on disk.');
        $this->assertEquals('martin', $reloadedConfig[Constants::CONFIG_APP_USER]);
        $this->assertEquals(5, $reloadedConfig[Constants::CONFIG_REMINDER_VALUE]);
        
        // ASSERT: Now that credentials exist, the Setup phase should be flagged as complete.
        $this->assertTrue($this->manager->isInitialized(), 'Providing credentials should mark the system as initialized.');
    }
}
