<?php
declare(strict_types=1);

namespace PhpBirthday;

/**
 * Authentication Manager
 * * Secures the application using standard HTTP Basic Authentication.
 * Designed to handle FastCGI edge cases often found on shared hostings.
 */
class AuthManager
{
    /**
     * Extracts credentials from server variables and validates them
     * against the application's secure configuration.
     * * Passing $serverVars explicitly avoids reading global state ($_SERVER),
     * enabling clean and isolated unit testing.
     * * @param array $config The application configuration map.
     * @param array $serverVars The server variables array (e.g., $_SERVER).
     * @return bool True if credentials are valid.
     */
    public function isAuthenticated(array $config, array $serverVars): bool
    {
        $authUser = '';
        $authPw = '';

        // Standard HTTP Auth Header extraction
        if (isset($serverVars['PHP_AUTH_USER'])) {
            $authUser = $serverVars['PHP_AUTH_USER'];
            $authPw = $serverVars['PHP_AUTH_PW'] ?? '';
        } 
        // Fallbacks for FastCGI environments (like OVH or o2switch) 
        // that strip PHP_AUTH headers by default.
        else {
            $header = $serverVars['HTTP_AUTHORIZATION'] ?? $serverVars['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (stripos($header, 'basic') === 0) {
                $decoded = base64_decode(substr($header, Constants::AUTH_HEADER_OFFSET));
                if (strpos($decoded, ':') !== false) {
                    list($authUser, $authPw) = explode(':', $decoded, 2);
                }
            }
        }

        $expectedUser = $config[Constants::CONFIG_APP_USER] ?? '';
        $expectedHash = $config[Constants::CONFIG_APP_PASS_HASH] ?? '';

        return ($authUser === $expectedUser && password_verify($authPw, $expectedHash));
    }

    /**
     * Emits the necessary HTTP headers to trigger the browser's 
     * native username/password dialog box.
     * * @param Translator $translator Used to localize the error message payload.
     */
    public function sendUnauthorizedHeaders(Translator $translator): void
    {
        header(sprintf(Constants::HEADER_AUTH_WWW, Constants::APP_NAME));
        header(Constants::HEADER_401_STATUS);
        echo $translator->get('ui_unauthorized');
        exit;
    }
}
