<?php
declare(strict_types=1);

namespace PhpBirthday\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpBirthday\AuthManager;
use PhpBirthday\Constants;

/**
 * Unit Tests for the AuthManager.
 * * Verifies that the Basic Authentication logic properly protects the web interface 
 * and the calendar feed against unauthorized access.
 */
#[CoversClass(AuthManager::class)]
class AuthManagerTest extends TestCase
{
    private AuthManager $authManager;

    protected function setUp(): void
    {
        $this->authManager = new AuthManager();
    }

    /**
     * Tests the scenario where a visitor attempts to access the page without 
     * providing any browser HTTP authentication headers.
     */
    public function testAccessDeniedWhenNoCredentialsProvidedByClient(): void
    {
        // ARRANGE: Simulate an empty $_SERVER array (no browser prompt filled).
        $serverVars = []; 
        $config = [
            Constants::CONFIG_APP_USER => 'admin',
            // PHP's password_hash automatically generates a secure bcrypt hash for testing.
            Constants::CONFIG_APP_PASS_HASH => password_hash('secret', PASSWORD_DEFAULT)
        ];

        // ACT & ASSERT: The manager must return false.
        $this->assertFalse(
            $this->authManager->isAuthenticated($config, $serverVars),
            'Should deny access if no HTTP Basic Auth headers are present.'
        );
    }

    /**
     * Tests the "Happy Flow" where the user provides the correct credentials.
     */
    public function testAccessGrantedWithValidCredentials(): void
    {
        // ARRANGE: Simulate a server receiving valid base64 decoded credentials from the browser.
        $serverVars = [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret'
        ];
        $config = [
            Constants::CONFIG_APP_USER => 'admin',
            Constants::CONFIG_APP_PASS_HASH => password_hash('secret', PASSWORD_DEFAULT)
        ];

        // ACT & ASSERT: password_verify() should match the raw input with the bcrypt hash.
        $this->assertTrue(
            $this->authManager->isAuthenticated($config, $serverVars),
            'Should grant access when valid credentials are provided.'
        );
    }

    /**
     * Tests brute-force or typo scenarios where the password does not match the hash.
     */
    public function testAccessDeniedWithInvalidPassword(): void
    {
        // ARRANGE: Correct username, wrong password.
        $serverVars = [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'wrong_password'
        ];
        $config = [
            Constants::CONFIG_APP_USER => 'admin',
            Constants::CONFIG_APP_PASS_HASH => password_hash('secret', PASSWORD_DEFAULT)
        ];

        // ACT & ASSERT
        $this->assertFalse(
            $this->authManager->isAuthenticated($config, $serverVars),
            'Should deny access if the password does not match the stored hash.'
        );
    }
}
