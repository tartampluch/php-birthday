<?php
declare(strict_types=1);

namespace PhpBirthday\Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpBirthday\Services\ICalGenerator;
use PhpBirthday\Models\Contact;
use PhpBirthday\Translator;
use PhpBirthday\Constants;
use DateTimeImmutable;

/**
 * Unit Tests for the ICalGenerator Service.
 * * This suite verifies the core business logic of the application: 
 * transforming parsed Contacts into a valid, RFC 5545 compliant iCalendar string.
 * It strictly tests the "Rolling Window" logic (Past Year, Current Year, Next Year)
 * and ensures dynamic age calculations are exact.
 */
#[CoversClass(ICalGenerator::class)]
class ICalGeneratorTest extends TestCase
{
    private ICalGenerator $generator;
    private array $baseConfig;

    /**
     * Prepares the testing environment.
     */
    protected function setUp(): void
    {
        // Create a smart Stub for the Translator.
        // Instead of returning hardcoded strings, we simulate the logic of a real language file 
        // to test if plural (ans), singular (an), and newborn (naissance) variations are triggered correctly.
        $translatorStub = $this->createStub(Translator::class);
        $translatorStub->method('get')->willReturnCallback(function($key, ...$args) {
            if ($key === 'event_summary_age') return $args[0] . ' (' . $args[1] . ' ans)';
            if ($key === 'event_summary_birth') return $args[0] . ' (naissance)';
            return $args[0];
        });

        $this->generator = new ICalGenerator($translatorStub);
        
        // Define a baseline dummy configuration representing user settings
        $this->baseConfig = [
            Constants::CONFIG_REMINDER_ENABLED => false,
            Constants::CONFIG_REMINDER_VALUE => 1,
            Constants::CONFIG_REMINDER_UNIT => Constants::UNIT_DAYS,
            Constants::CONFIG_REMINDER_DIR => Constants::DIR_BEFORE,
        ];
    }

    /**
     * Tests the core feature: discarding static 'RRULE' recurrences in favor of 
     * generating distinct, individual events with mathematically accurate ages.
     */
    public function testRollingWindowGeneratesThreeEventsWithCorrectAge(): void
    {
        // ARRANGE: Create a fictional contact born exactly 10 years ago from today's year.
        $birthYear = (int)date('Y') - 10;
        $contact = new Contact('uid-123', 'Paul Test', new DateTimeImmutable("$birthYear-05-10"), true);
        
        // ACT: Generate the iCalendar payload.
        $icsOutput = $this->generator->generate([$contact], $this->baseConfig);
        
        // ASSERT: The output must contain exactly 3 VEVENT blocks (Last year, this year, next year).
        $this->assertEquals(3, substr_count($icsOutput, 'BEGIN:VEVENT'), 'The generator should output a 3-year sliding window.');
        
        // ASSERT: Check that the dynamic string injection accurately calculated the age for the current year.
        $this->assertStringContainsString('SUMMARY:Paul Test (10 ans)', $icsOutput, 'The age should be exactly 10 for the current year block.');
    }

    /**
     * Tests the guard clause that prevents negative ages from appearing in the calendar 
     * if the person hasn't been born yet in the target year of the rolling window.
     */
    public function testNegativeAgePreventionForNewborns(): void
    {
        // ARRANGE: A baby born THIS current year.
        $currentYear = (int)date('Y');
        $contact = new Contact('uid-456', 'Baby Doe', new DateTimeImmutable("$currentYear-08-20"), true);

        // ACT
        $icsOutput = $this->generator->generate([$contact], $this->baseConfig);
        
        // ASSERT: The generator should SKIP the "Past Year" event because the baby was not born yet.
        $this->assertEquals(2, substr_count($icsOutput, 'BEGIN:VEVENT'), 'Should only generate events for Current Year and Next Year.');
        
        // ASSERT: The current year should trigger the "birth" specific translation string.
        $this->assertStringContainsString('SUMMARY:Baby Doe (naissance)', $icsOutput, 'Age 0 should trigger the birth translation variation.');
    }

    /**
     * Tests if the VALARM (Alarm/Reminder) property is accurately injected into 
     * the payload when the user enables it in the configuration.
     */
    public function testAlarmsAreGeneratedWhenRemindersEnabled(): void
    {
        // ARRANGE: Override the base configuration to turn reminders ON (1 Day Before).
        $config = $this->baseConfig;
        $config[Constants::CONFIG_REMINDER_ENABLED] = true;
        
        $contact = new Contact('uid-789', 'Alarm Bob', new DateTimeImmutable("2000-01-01"), true);

        // ACT
        $icsOutput = $this->generator->generate([$contact], $config);

        // ASSERT: Verify the VALARM block and its specific ISO 8601 duration string exist.
        $this->assertStringContainsString('BEGIN:VALARM', $icsOutput, 'The VALARM block must be injected when enabled.');
        $this->assertStringContainsString('TRIGGER:-P1D', $icsOutput, 'The trigger logic must compile to a valid negative duration (-P1D = 1 Day Before).');
    }

    /**
     * Tests the sanitizer to ensure symbols that break the RFC 5545 parser are escaped.
     */
    public function testForbiddenCharactersAreEscapedCorrectly(): void
    {
        // ARRANGE: A contact name containing commas and semicolons (highly illegal in iCalendar values).
        $contact = new Contact('uid-999', 'Doe, John; Jr.', new DateTimeImmutable("2000-01-01"), true);
        
        // ACT
        $icsOutput = $this->generator->generate([$contact], $this->baseConfig);

        // ASSERT: Commas and semicolons must be prefixed with a backslash.
        $this->assertStringContainsString('SUMMARY:Doe\, John\; Jr.', $icsOutput, 'Forbidden characters must be strictly escaped.');
    }
}
