<?php
declare(strict_types=1);

namespace PhpBirthday\Services;

use RuntimeException;
use PhpBirthday\Constants;
use PhpBirthday\Translator;

/**
 * Data Retrieval Service
 * * Responsible for securely fetching the vCard payload from a remote URL 
 * (like a CardDAV server) or reading a local uploaded file.
 */
class VCardFetcher
{
    private Translator $translator;

    /**
     * @param Translator $translator Injected translator for localized error messages.
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Fetches the raw string payload.
     * * @param string $url The source URL or local file path.
     * @param string $username Optional basic auth username.
     * @param string $password Optional basic auth password.
     * @return string The raw vCard content.
     * @throws RuntimeException on network, authentication, or file system failures.
     */
    public function fetch(string $url, string $username = '', string $password = ''): string
    {
        // Handle local files (e.g., uploaded VCF) by checking protocol prefix
        if (!str_starts_with($url, Constants::PROTOCOL_HTTP) && !str_starts_with($url, Constants::PROTOCOL_HTTPS)) {
            $content = @file_get_contents($url);
            if ($content === false) {
                throw new RuntimeException($this->translator->get('err_local_file', $url));
            }
            return $content;
        }

        // Handle remote CardDAV URLs via cURL (standard across shared hostings)
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException($this->translator->get('err_curl_init'));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, Constants::CURL_TIMEOUT_SECONDS);
        
        // Inject Basic Authentication if credentials are provided
        if ($username !== '' && $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= Constants::HTTP_STATUS_ERROR_THRESHOLD) {
            throw new RuntimeException($this->translator->get('err_http_fetch', $httpCode, $error));
        }

        return (string) $response;
    }
}
