<?php
declare(strict_types=1);

namespace PhpBirthday\Services;

use RuntimeException;
use DOMDocument;
use DOMXPath;
use PhpBirthday\Constants;
use PhpBirthday\Translator;

/**
 * Data Retrieval Service
 * Responsible for securely fetching the vCard payload from a local file,
 * a standard HTTP URL, or natively querying a CardDAV server via RFC 6352.
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
     * @param bool $isCardDav If true, uses the CardDAV REPORT method instead of a GET.
     * @return string The raw vCard(s) content concatenated.
     * @throws RuntimeException on network, authentication, or parsing failures.
     */
    public function fetch(string $url, string $username = '', string $password = '', bool $isCardDav = false): string
    {
        // 1. Handle local files
        if (!str_starts_with($url, Constants::PROTOCOL_HTTP) && !str_starts_with($url, Constants::PROTOCOL_HTTPS)) {
            $content = @file_get_contents($url);
            if ($content === false) {
                throw new RuntimeException($this->translator->get('err_local_file', $url));
            }
            return $content;
        }

        // 2. Prepare cURL for remote URLs
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException($this->translator->get('err_curl_init'));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, Constants::CURL_TIMEOUT_SECONDS);
        
        // Inject Basic Authentication
        if ($username !== '' && $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        }

        // Standalone SSL Verification
        $caCertPath = dirname(__DIR__, 2) . '/data/cacert.pem';
        if (file_exists($caCertPath)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
        }

        // Native CardDAV Implementation (RFC 6352)
        if ($isCardDav) {
            // The precise XML payload to request all vCards in an address book
            $xmlPayload = <<<XML
<?xml version="1.0" encoding="utf-8" ?>
<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
    <d:prop>
        <card:address-data />
    </d:prop>
</card:addressbook-query>
XML;
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'REPORT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml; charset=utf-8',
                'Depth: 1' // Required by WebDAV to query children of the address book
            ]);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // A standard GET returns 200 OK. A CardDAV REPORT returns 207 Multi-Status.
        if ($response === false || $httpCode >= Constants::HTTP_STATUS_ERROR_THRESHOLD) {
            throw new RuntimeException($this->translator->get('err_http_fetch', $httpCode, $error));
        }

        // If it was a CardDAV request, we must extract the vCards from the XML
        if ($isCardDav) {
            return $this->extractVCardsFromCardDavResponse((string) $response);
        }

        return (string) $response;
    }

    /**
     * Parses a WebDAV 207 Multi-Status XML response and extracts the raw vCards.
     */
    private function extractVCardsFromCardDavResponse(string $xmlContent): string
    {
        $dom = new DOMDocument();
        
        // Suppress XML parsing warnings in case the server returns malformed data
        $internalErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmlContent);
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            throw new RuntimeException("CardDAV Error: The server did not return valid XML.");
        }

        $xpath = new DOMXPath($dom);
        // Register the CardDAV namespace to allow XPath querying
        $xpath->registerNamespace('card', 'urn:ietf:params:xml:ns:carddav');

        // Query all <card:address-data> nodes
        $nodes = $xpath->query('//card:address-data');
        
        if ($nodes === false || $nodes->length === 0) {
            throw new RuntimeException("CardDAV Error: No contacts found in this address book.");
        }

        $vCards = [];
        foreach ($nodes as $node) {
            $vCards[] = trim($node->nodeValue);
        }

        // Concatenate all individual vCards into one large string 
        // to match the format expected by our VCardParser
        return implode("\n", $vCards);
    }
}
