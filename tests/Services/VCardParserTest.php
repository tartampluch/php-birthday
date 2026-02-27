<?php
declare(strict_types=1);

namespace PhpBirthday\Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpBirthday\Services\VCardParser;
use PhpBirthday\Translator;

/**
 * Unit Tests for the VCardParser Service.
 * * This test suite ensures that raw vCard strings (like those exported from Google 
 * Contacts or Apple iCloud) are correctly parsed and converted into robust Contact Data 
 * Transfer Objects (DTOs). It specifically tests edge cases like leap years and 
 * proprietary formatting variations.
 */
#[CoversClass(VCardParser::class)]
class VCardParserTest extends TestCase
{
    private VCardParser $parser;

    /**
     * The setUp() method is automatically called by PHPUnit BEFORE each individual test.
     * We use it to instantiate our parser in a clean, isolated state.
     */
    protected function setUp(): void
    {
        // To isolate the Parser, we do not use the real Translator (which reads real JSON files).
        // Instead, we use a "Stub": a fake object that just simulates the expected behavior.
        $translatorStub = $this->createStub(Translator::class);
        
        // We configure the stub: if the parser asks for the 'unknown_name' translation, 
        // return the string 'Unknown'. For any other key, just return the key itself.
        $translatorStub->method('get')->willReturnCallback(fn($key) => $key === 'unknown_name' ? 'Unknown' : $key);
        
        $this->parser = new VCardParser($translatorStub);
    }

    /**
     * Tests the "Happy Flow" where a vCard contains perfectly formatted standard data.
     */
    public function testParseStandardVCardWithFullBirthday(): void
    {
        // 1. ARRANGE: Prepare the raw input data representing a standard vCard.
        $vcard = <<<VCARD
        BEGIN:VCARD
        VERSION:3.0
        FN:John Doe
        BDAY:1990-05-15
        END:VCARD
        VCARD;

        // 2. ACT: Pass the raw string to our parser.
        $contacts = $this->parser->parse($vcard);

        // 3. ASSERT: Verify the resulting Contact array matches our expectations.
        $this->assertCount(1, $contacts, 'The parser should successfully extract exactly one contact.');
        $this->assertEquals('John Doe', $contacts[0]->fullName, 'The Full Name (FN) should be extracted correctly.');
        $this->assertTrue($contacts[0]->hasKnownYear, 'A full YYYY-MM-DD date means the birth year is known.');
    }

    /**
     * Tests a specific edge case common to Apple Contacts where the user only 
     * entered the month and day, omitting the year.
     */
    public function testParseAppleStyleYearlessBirthday(): void
    {
        // 1. ARRANGE: Note the '--MM-DD' format which represents an unknown year.
        $vcard = <<<VCARD
        BEGIN:VCARD
        VERSION:3.0
        FN:Jane Smith
        BDAY:--12-25
        END:VCARD
        VCARD;

        // 2. ACT
        $contacts = $this->parser->parse($vcard);

        // 3. ASSERT
        $this->assertCount(1, $contacts);
        $this->assertFalse($contacts[0]->hasKnownYear, 'The parser must flag this contact as having an unknown year.');
        $this->assertEquals('12', $contacts[0]->birthday->format('m'), 'The month should be parsed as December.');
        $this->assertEquals('25', $contacts[0]->birthday->format('d'), 'The day should be parsed as the 25th.');
    }

    /**
     * Tests the notoriously difficult handling of February 29th (Leap Years).
     */
    public function testLeapYearDateHandling(): void
    {
        // ARRANGE & ACT: Test 1 - A known leap year (1992 is a leap year)
        $vcard1 = "BEGIN:VCARD\nFN:Leap Year Bob\nBDAY:1992-02-29\nEND:VCARD";
        $contacts1 = $this->parser->parse($vcard1);
        
        // ASSERT: Ensure the date was not altered or shifted to March 1st by PHP's DateTime parser.
        $this->assertEquals('02-29', $contacts1[0]->birthday->format('m-d'), 'A valid leap year date should remain Feb 29th.');

        // ARRANGE & ACT: Test 2 - A yearless leap year (e.g., Apple Contacts)
        $vcard2 = "BEGIN:VCARD\nFN:Yearless Leap Alice\nBDAY:--02-29\nEND:VCARD";
        $contacts2 = $this->parser->parse($vcard2);
        
        // ASSERT: The parser must inject a default leap year (like 2000) under the hood 
        // to prevent PHP from throwing an "invalid date" exception.
        $this->assertEquals('02-29', $contacts2[0]->birthday->format('m-d'), 'Yearless leap dates must be protected and preserved.');
    }

    /**
     * Tests the parser's resilience when a contact is missing the mandatory FN (Full Name) property.
     */
    public function testMissingNameFallsBackToTranslator(): void
    {
        // ARRANGE: A valid birthday, but the FN property is completely missing.
        $vcard = "BEGIN:VCARD\nBDAY:1980-01-01\nEND:VCARD";
        
        // ACT
        $contacts = $this->parser->parse($vcard);

        // ASSERT: The parser should have used our Translator stub to inject the 'Unknown' placeholder.
        $this->assertCount(1, $contacts);
        $this->assertEquals('Unknown', $contacts[0]->fullName, 'Missing names should gracefully fallback to the translated placeholder.');
    }

    /**
     * Tests that contacts purely containing phone numbers/emails without a birthday 
     * are completely ignored to avoid cluttering the calendar.
     */
    public function testIgnoreContactsWithoutBirthday(): void
    {
        $vcard = "BEGIN:VCARD\nFN:No Birthday Bob\nEND:VCARD";
        $this->assertCount(0, $this->parser->parse($vcard), 'Contacts lacking a BDAY property must be excluded from the final array.');
    }
}
