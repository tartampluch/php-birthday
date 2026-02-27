<?php
declare(strict_types=1);

namespace PhpBirthday\Models;

use DateTimeImmutable;

/**
 * Contact Domain Model (Data Transfer Object)
 * * Represents a parsed contact entity ready for calendar generation.
 * This class ensures type safety throughout the application's pipeline.
 */
class Contact
{
    public string $uid;
    public string $fullName;
    public DateTimeImmutable $birthday;
    public bool $hasKnownYear;

    /**
     * @param string $uid A unique identifier derived deterministically.
     * @param string $fullName The extracted full name of the contact.
     * @param DateTimeImmutable $birthday The parsed birthday date.
     * @param bool $hasKnownYear Indicates if the original date had a real year.
     */
    public function __construct(string $uid, string $fullName, DateTimeImmutable $birthday, bool $hasKnownYear)
    {
        $this->uid = $uid;
        $this->fullName = $fullName;
        $this->birthday = $birthday;
        $this->hasKnownYear = $hasKnownYear;
    }
}
