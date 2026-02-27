<?php
declare(strict_types=1);

namespace PhpBirthday;

use Throwable;
use PhpBirthday\Services\VCardFetcher;
use PhpBirthday\Services\VCardParser;
use PhpBirthday\Services\ICalGenerator;

/**
 * Main Application Orchestrator
 * * Connects the Domain Services together and handles high-performance APCu caching.
 * Follows Dependency Injection principles for complete unit testability.
 */
class Application
{
    private ConfigManager $configManager;
    private Translator $translator;

    /**
     * @param ConfigManager $configManager Handles application settings.
     * @param Translator $translator Handles UI and error localization.
     */
    public function __construct(ConfigManager $configManager, Translator $translator)
    {
        $this->configManager = $configManager;
        $this->translator = $translator;
    }

    /**
     * Executes the main application pipeline: 
     * Cache verification -> Fetching -> Parsing -> Generating -> Outputting.
     * * @param array $serverVars HTTP Server variables injected for testability.
     */
    public function run(array $serverVars = []): void
    {
        try {
            $config = $this->configManager->load();
            
            // Resolve correct data source based on user configuration
            $sourceUrl = $config[Constants::CONFIG_MODE] === Constants::MODE_LOCAL 
                ? Constants::PATH_UPLOADED_VCF 
                : $config[Constants::CONFIG_URL];

            // Resolve dynamic Cache TTL based on user preference
            $ttlMinutes = (int)($config[Constants::CONFIG_REFRESH_INTERVAL] ?? Constants::DEFAULT_REFRESH_MINUTES);
            $ttlSeconds = $ttlMinutes * 60;

            // Create a deterministic cache key tied to the URL and Language
            $cacheKey = Constants::CACHE_PREFIX . md5($sourceUrl . $config[Constants::CONFIG_LANG]);
            $cachedData = null;
            
            // Enable cache retrieval only if APCu is active AND user interval > 0
            $useCache = function_exists('apcu_fetch') && $ttlSeconds > 0;

            // 1. Attempt rapid RAM retrieval
            if ($useCache) {
                $cachedData = apcu_fetch($cacheKey);
            }

            // 2. Cache Hit -> fast track response
            if ($cachedData !== false && is_array($cachedData)) {
                $icsContent = $cachedData['content'];
                $timestamp = $cachedData['timestamp'];
            } 
            // 3. Cache Miss -> execute full heavy pipeline
            else {
                $fetcher = new VCardFetcher($this->translator);
                $rawVcard = $fetcher->fetch($sourceUrl, $config[Constants::CONFIG_USER], $config[Constants::CONFIG_PASS]);

                $parser = new VCardParser($this->translator);
                $contacts = $parser->parse($rawVcard);

                $generator = new ICalGenerator($this->translator);
                $icsContent = $generator->generate($contacts, $config);
                $timestamp = time();

                // Store in cache with the dynamically computed TTL
                if ($useCache) {
                    apcu_store($cacheKey, [
                        'content' => $icsContent,
                        'timestamp' => $timestamp
                    ], $ttlSeconds);
                }
            }

            $this->sendResponse($icsContent, $timestamp, $ttlSeconds, $serverVars);

        } catch (Throwable $e) {
            $this->sendError($e->getMessage());
        }
    }

    /**
     * Dispatches the successful HTTP payload. 
     * Abstracted into a protected method to allow interception by unit test wrappers.
     * * @param string $content The generated iCalendar feed.
     * @param int $timestamp UNIX timestamp of the generation time.
     * @param int $ttlSeconds The cache duration configured by the user.
     * @param array $serverVars Server variables to check HTTP cache headers.
     */
    protected function sendResponse(string $content, int $timestamp, int $ttlSeconds, array $serverVars): void
    {
        $lastModifiedStr = gmdate(Constants::FORMAT_HTTP_DATE, $timestamp) . ' GMT';

        // HTTP 304 Bandwidth Optimization Check (If-Modified-Since validation)
        if (isset($serverVars['HTTP_IF_MODIFIED_SINCE'])) {
            $ifModifiedSince = strtotime($serverVars['HTTP_IF_MODIFIED_SINCE']);
            if ($ifModifiedSince !== false && $ifModifiedSince >= $timestamp) {
                header(Constants::HEADER_304_STATUS);
                exit; // Abort cleanly, instructing client to use its local cache
            }
        }

        header(Constants::HEADER_CONTENT_ICAL);
        header(Constants::HEADER_DISPOSITION);
        
        // Dynamically assign HTTP Cache headers based on user configuration
        if ($ttlSeconds > 0) {
            header(sprintf(Constants::HEADER_CACHE_CONTROL_FORMAT, $ttlSeconds));
        } else {
            header(Constants::HEADER_NO_CACHE);
        }
        
        header('Last-Modified: ' . $lastModifiedStr);
        
        echo $content;
    }

    /**
     * Gracefully handles and exposes application errors.
     * * @param string $message The exception message to localize and display.
     */
    protected function sendError(string $message): void
    {
        header(Constants::HEADER_500_STATUS);
        header(Constants::HEADER_PLAIN_TYPE);
        echo $this->translator->get('err_general', $message);
        exit(1);
    }
}
