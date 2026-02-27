<?php
declare(strict_types=1);

namespace PhpBirthday\Services;

use DateTimeImmutable;
use DateTimeZone;
use PhpBirthday\Constants;
use PhpBirthday\Translator;

/**
 * iCalendar Output Generator
 * * Adheres strictly to RFC 5545. 
 * Replicates the "Go" architecture by generating a rolling window of events 
 * (Past, Current, Next Year) to allow dynamic age injection inside the event title.
 */
class ICalGenerator
{
    private Translator $translator;

    /**
     * @param Translator $translator Injected translator for localized event summaries.
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Compiles the array of Contacts into a valid .ics payload string.
     * * @param array $contacts List of parsed Contact objects.
     * @param array $config Application configuration to determine reminder behavior.
     * @return string The raw iCalendar payload.
     */
    public function generate(array $contacts, array $config): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(Constants::TIMEZONE_UTC));
        $dtstamp = $now->format(Constants::FORMAT_DTSTAMP);
        
        // Rolling window: Generate for the previous, current, and next year
        // This allows calendar clients to see past and future instances immediately without RRULEs.
        $currentYear = (int)$now->format('Y');
        $targetYears = [$currentYear - 1, $currentYear, $currentYear + 1];
        
        $remindersEnabled = (bool) $config[Constants::CONFIG_REMINDER_ENABLED];

        $output = [];
        $output[] = Constants::ICAL_BEGIN_VCAL;
        $output[] = Constants::ICAL_VERSION;
        $output[] = Constants::ICAL_PRODID;
        $output[] = Constants::ICAL_CALSCALE;
        $output[] = Constants::ICAL_CALNAME;
        $output[] = Constants::ICAL_REFRESH;

        foreach ($contacts as $contact) {
            $birthYear = (int)$contact->birthday->format('Y');
            $birthMonth = (int)$contact->birthday->format('m');
            $birthDay = (int)$contact->birthday->format('d');
            $escapedName = $this->escapeIcalText($contact->fullName);

            foreach ($targetYears as $year) {
                // Guard: Do not generate an event if the person is not born yet in this target year
                if ($contact->hasKnownYear && $year < $birthYear) {
                    continue;
                }

                // Determine dynamic Age
                $age = $contact->hasKnownYear ? ($year - $birthYear) : null;

                // Format the title accurately based on the exact age that year
                if ($age === null) {
                    $summaryText = $this->translator->get('event_summary', $escapedName);
                } elseif ($age === 0) {
                    $summaryText = $this->translator->get('event_summary_birth', $escapedName);
                } elseif ($age === 1) {
                    $summaryText = $this->translator->get('event_summary_age_1', $escapedName, $age);
                } else {
                    $summaryText = $this->translator->get('event_summary_age', $escapedName, $age);
                }

                // Construct event date for the target year
                // PHP's setDate naturally handles leap year roll-overs (e.g. 2025-02-29 becomes 2025-03-01)
                $eventDate = (new DateTimeImmutable('now', new DateTimeZone(Constants::TIMEZONE_UTC)))
                    ->setDate($year, $birthMonth, $birthDay)
                    ->setTime(Constants::TIME_HOUR_RESET, Constants::TIME_MINUTE_RESET, Constants::TIME_SECOND_RESET);
                    
                $dtstart = $eventDate->format(Constants::FORMAT_DTSTART);
                
                // Ensure UID uniqueness per year to prevent calendar clients from squashing events together
                $uid = sprintf("%s-%d%s", $contact->uid, $year, Constants::UID_DOMAIN);

                $output[] = Constants::ICAL_BEGIN_VEVENT;
                $output[] = Constants::ICAL_UID . $uid;
                $output[] = Constants::ICAL_DTSTAMP . $dtstamp;
                $output[] = Constants::ICAL_DTSTART . $dtstart;
                $output[] = Constants::ICAL_SUMMARY . $summaryText;
                $output[] = Constants::ICAL_TRANSP;

                // Inject VALARM standard alarm trigger if configured by the user
                if ($remindersEnabled) {
                    $output[] = Constants::ICAL_BEGIN_VALARM;
                    $output[] = Constants::ICAL_ACTION_DISPLAY;
                    $output[] = Constants::ICAL_DESCRIPTION . $summaryText;
                    $output[] = Constants::ICAL_TRIGGER . $this->buildTriggerDuration($config);
                    $output[] = Constants::ICAL_END_VALARM;
                }

                $output[] = Constants::ICAL_END_VEVENT;
            }
        }

        $output[] = Constants::ICAL_END_VCAL;

        return implode(Constants::ICAL_CRLF, $output) . Constants::ICAL_CRLF;
    }

    /**
     * Compiles an ISO 8601 duration string required by the iCalendar TRIGGER property.
     * * @param array $config The application configuration containing reminder settings.
     * @return string Formatted duration (e.g., -P1D for 1 day before).
     */
    private function buildTriggerDuration(array $config): string
    {
        $val = (int) $config[Constants::CONFIG_REMINDER_VALUE];
        $unit = $config[Constants::CONFIG_REMINDER_UNIT];
        $dir = $config[Constants::CONFIG_REMINDER_DIR];

        // Occurrences triggering "before" the event start require a leading minus sign
        $prefix = ($dir === Constants::DIR_BEFORE) ? '-' : '';

        if ($unit === Constants::UNIT_DAYS) {
            return sprintf('%sP%dD', $prefix, $val);
        } elseif ($unit === Constants::UNIT_HOURS) {
            return sprintf('%sPT%dH', $prefix, $val);
        } else {
            return sprintf('%sPT%dM', $prefix, $val);
        }
    }

    /**
     * Replaces characters forbidden by RFC 5545 with their escaped equivalents.
     * * @param string $text Raw text.
     * @return string Escaped text safe for iCalendar injection.
     */
    private function escapeIcalText(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }
}
