<?php
declare(strict_types=1);

namespace PhpBirthday\Services;

use DateTimeImmutable;
use PhpBirthday\Models\Contact;
use PhpBirthday\Constants;
use PhpBirthday\Translator;

/**
 * Parsing Service
 * * Extracts robust Contact entities from raw vCard string payloads.
 * Handles various Apple and Google vCard formatting quirks.
 */
class VCardParser
{
    private Translator $translator;

    /**
     * @param Translator $translator Injected translator for fallback text (e.g., 'Unknown Name').
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Parses the raw vCard string and extracts valid birthdays.
     * * @param string $vcardContent The raw vCard payload.
     * @return Contact[] List of successfully parsed contacts containing birthdays.
     */
    public function parse(string $vcardContent): array
    {
        $contacts = [];
        // Normalize line endings to LF to prevent regex failures
        $normalizedContent = str_replace([Constants::CHAR_CRLF, Constants::CHAR_CR], Constants::CHAR_LF, $vcardContent);
        $lines = explode(Constants::CHAR_LF, $normalizedContent);

        $currentName = $this->translator->get('unknown_name');
        $currentBirthday = null;
        $inVcard = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (str_starts_with(strtoupper($line), Constants::STRING_VCARD_BEGIN)) {
                $inVcard = true;
                $currentName = $this->translator->get('unknown_name');
                $currentBirthday = null;
                continue;
            }

            if (!$inVcard) { continue; }

            if (str_starts_with(strtoupper($line), Constants::STRING_VCARD_END)) {
                $inVcard = false;
                if ($currentBirthday !== null) {
                    // Create a deterministic UID based on name to prevent duplicates on client side
                    $uid = md5(Constants::UID_PREFIX . $currentName);
                    $contacts[] = new Contact($uid, $currentName, $currentBirthday['date'], $currentBirthday['hasYear']);
                }
                continue;
            }

            // Extract Full Name (FN property)
            if (preg_match(Constants::REGEX_FN, $line, $matches)) {
                $currentName = trim($matches[1]);
            }

            // Extract Birthday (BDAY property)
            if (preg_match(Constants::REGEX_BDAY, $line, $matches)) {
                $rawBday = trim($matches[1]);
                $parsedBday = $this->parseDate($rawBday);
                if ($parsedBday !== null) {
                    $currentBirthday = $parsedBday;
                }
            }
        }

        return $contacts;
    }

    /**
     * Normalizes highly varied vCard date string formats into strict DateTime objects.
     * Supports standard YYYY-MM-DD and Apple's yearless --MM-DD formats.
     * * @param string $dateStr The raw date string.
     * @return array|null Associative array containing the DateTime object and a boolean flag.
     */
    private function parseDate(string $dateStr): ?array
    {
        // Strip out the time segment since we only require the date for birthdays
        if (($pos = strpos($dateStr, Constants::CHAR_T_TIME_SEP)) !== false) {
            $dateStr = substr($dateStr, 0, $pos);
        }

        $hasYear = true;
        // Default to a leap year so Feb 29th evaluates correctly for yearless inputs
        $year = Constants::DEFAULT_LEAP_YEAR; 
        $month = Constants::DEFAULT_MONTH;
        $day = Constants::DEFAULT_DAY;

        if (preg_match(Constants::REGEX_DATE_NO_YEAR, $dateStr, $matches)) {
            $hasYear = false;
            $month = (int)$matches[1];
            $day = (int)$matches[2];
        } 
        elseif (preg_match(Constants::REGEX_DATE_FULL, $dateStr, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];
        } else {
            return null; // Unsupported syntax
        }

        $dateTime = (new DateTimeImmutable())
            ->setDate($year, $month, $day)
            ->setTime(Constants::TIME_HOUR_RESET, Constants::TIME_MINUTE_RESET, Constants::TIME_SECOND_RESET);

        return ['date' => $dateTime, 'hasYear' => $hasYear];
    }
}
