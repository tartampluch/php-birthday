<?php
declare(strict_types=1);

namespace PhpBirthday;

/**
 * PHP Birthday - System Constants
 * * Centralizes all magic strings, numerical values, regex patterns, form keys, 
 * and HTTP codes. This ensures absolute consistency across the application 
 * and prevents hardcoded values, making global updates extremely safe and simple.
 */
class Constants
{
    public const APP_NAME = 'PHP Birthday';
    public const APP_VERSION = '1.1.0';

    // ------------------------------------------------------------------------
    // System Paths & Permissions
    // ------------------------------------------------------------------------
    public const PATH_DATA_DIR = __DIR__ . '/../data';
    public const PATH_CONFIG_FILE = self::PATH_DATA_DIR . '/config.json';
    public const PATH_UPLOADED_VCF = self::PATH_DATA_DIR . '/contacts.vcf';
    public const PATH_LOCALES_DIR = __DIR__ . '/../locales/';
    public const DIR_PERMISSIONS = 0755;

    // ------------------------------------------------------------------------
    // Configuration & Form Keys
    // ------------------------------------------------------------------------
    public const CONFIG_LANG = 'language';
    public const CONFIG_MODE = 'source_mode';
    public const CONFIG_URL = 'source_url';
    public const CONFIG_USER = 'username';
    public const CONFIG_PASS = 'password';
    public const CONFIG_REFRESH_INTERVAL = 'refresh_interval';
    
    // Security & Auth Keys
    public const CONFIG_APP_USER = 'app_username';
    public const CONFIG_APP_PASS_HASH = 'app_password_hash';

    // Reminder Configuration Keys
    public const CONFIG_REMINDER_ENABLED = 'reminder_enabled';
    public const CONFIG_REMINDER_VALUE = 'reminder_value';
    public const CONFIG_REMINDER_UNIT = 'reminder_unit';
    public const CONFIG_REMINDER_DIR = 'reminder_dir';

    // Setup & Action Form specific keys
    public const FORM_SETUP_USER = 'setup_user';
    public const FORM_SETUP_PASS = 'setup_pass';
    public const FORM_FILE_UPLOAD = 'vcf_file';
    public const FORM_ACTION = 'action';
    
    public const ACTION_REFRESH = 'refresh';
    public const ACTION_SAVE = 'save';

    // Translation internal keys
    public const I18N_LANG_NAME = 'language_name';

    // Defaults & Enums
    public const MODE_URL = 'url';
    public const MODE_LOCAL = 'local';
    public const DEFAULT_LANG = 'en';
    public const DEFAULT_REFRESH_MINUTES = 60; // 1 hour
    
    public const UNIT_DAYS = 'd';
    public const UNIT_HOURS = 'h';
    public const UNIT_MINUTES = 'm';
    public const DIR_BEFORE = 'before';
    public const DIR_AFTER = 'after';

    // ------------------------------------------------------------------------
    // Cache Parameters (APCu)
    // ------------------------------------------------------------------------
    public const CACHE_PREFIX = 'phpbirthday_';

    // ------------------------------------------------------------------------
    // Time and Date Processing Variables
    // ------------------------------------------------------------------------
    // Ensures February 29th evaluates correctly for yearless dates (e.g., --02-29)
    public const DEFAULT_LEAP_YEAR = 2000; 
    public const DEFAULT_MONTH = 1;
    public const DEFAULT_DAY = 1;
    public const TIME_HOUR_RESET = 0;
    public const TIME_MINUTE_RESET = 0;
    public const TIME_SECOND_RESET = 0;

    // ------------------------------------------------------------------------
    // Network, Auth & HTTP Standard Codes
    // ------------------------------------------------------------------------
    public const CURL_TIMEOUT_SECONDS = 30;
    public const AUTH_HEADER_OFFSET = 6; // Length of "Basic " string
    public const PROTOCOL_HTTP = 'http://';
    public const PROTOCOL_HTTPS = 'https://';
    public const HTTP_METHOD_POST = 'POST';

    public const HTTP_STATUS_OK = 200;
    public const HTTP_STATUS_NOT_MODIFIED = 304;
    public const HTTP_STATUS_UNAUTHORIZED = 401;
    public const HTTP_STATUS_ERROR_THRESHOLD = 400;
    public const HTTP_STATUS_500 = 500;
    public const HTTP_STATUS_503 = 503;

    // ------------------------------------------------------------------------
    // Regular Expressions & Parsing Strings
    // ------------------------------------------------------------------------
    public const REGEX_FN = '/^FN(?:;[^:]*)?:(.*)$/i';
    public const REGEX_BDAY = '/^BDAY(?:;[^:]*)?:(.*)$/i';
    public const REGEX_DATE_NO_YEAR = '/^--(\d{2})-?(\d{2})$/';
    public const REGEX_DATE_FULL = '/^(\d{4})-?(\d{2})-?(\d{2})$/';
    
    public const STRING_VCARD_BEGIN = 'BEGIN:VCARD';
    public const STRING_VCARD_END = 'END:VCARD';
    public const CHAR_T_TIME_SEP = 'T';

    // ------------------------------------------------------------------------
    // iCalendar (RFC 5545) Formats & Identifiers
    // ------------------------------------------------------------------------
    public const UID_PREFIX = 'v1-birthday-';
    public const UID_DOMAIN = '@phpbirthday';
    public const FORMAT_DTSTAMP = 'Ymd\THis\Z';
    public const FORMAT_DTSTART = 'Ymd';
    public const FORMAT_HTTP_DATE = 'D, d M Y H:i:s';
    public const TIMEZONE_UTC = 'UTC';

    public const ICAL_BEGIN_VCAL = 'BEGIN:VCALENDAR';
    public const ICAL_END_VCAL = 'END:VCALENDAR';
    public const ICAL_VERSION = 'VERSION:2.0';
    // FIXED: Added the required 'PRODID:' property key
    public const ICAL_PRODID = 'PRODID:-//PHP Birthday//Web Engine//EN';
    public const ICAL_CALSCALE = 'CALSCALE:GREGORIAN';
    public const ICAL_CALNAME = 'X-WR-CALNAME:Birthdays';
    public const ICAL_REFRESH = 'REFRESH-INTERVAL;VALUE=DURATION:P1D';
    public const ICAL_BEGIN_VEVENT = 'BEGIN:VEVENT';
    public const ICAL_END_VEVENT = 'END:VEVENT';
    public const ICAL_BEGIN_VALARM = 'BEGIN:VALARM';
    public const ICAL_END_VALARM = 'END:VALARM';
    public const ICAL_ACTION_DISPLAY = 'ACTION:DISPLAY';
    public const ICAL_UID = 'UID:';
    public const ICAL_DTSTAMP = 'DTSTAMP:';
    public const ICAL_DTSTART = 'DTSTART;VALUE=DATE:';
    public const ICAL_SUMMARY = 'SUMMARY:';
    public const ICAL_DESCRIPTION = 'DESCRIPTION:';
    public const ICAL_TRIGGER = 'TRIGGER:';
    public const ICAL_TRANSP = 'TRANSP:TRANSPARENT';
    public const ICAL_CRLF = "\r\n";
    public const CHAR_LF = "\n";
    public const CHAR_CRLF = "\r\n";
    public const CHAR_CR = "\r";

    // ------------------------------------------------------------------------
    // Pre-computed HTTP Headers
    // ------------------------------------------------------------------------
    public const HEADER_CONTENT_ICAL = 'Content-Type: text/calendar; charset=utf-8';
    public const HEADER_DISPOSITION = 'Content-Disposition: attachment; filename="birthdays.ics"';
    public const HEADER_CACHE_CONTROL_FORMAT = 'Cache-Control: private, max-age=%d, must-revalidate';
    public const HEADER_NO_CACHE = 'Cache-Control: no-cache, no-store, must-revalidate';
    public const HEADER_AUTH_WWW = 'WWW-Authenticate: Basic realm="%s"';
    
    public const HEADER_304_STATUS = 'HTTP/1.1 304 Not Modified';
    public const HEADER_401_STATUS = 'HTTP/1.0 401 Unauthorized';
    public const HEADER_500_STATUS = 'HTTP/1.1 500 Internal Server Error';
    public const HEADER_503_STATUS = 'HTTP/1.1 503 Service Unavailable';
    
    public const HEADER_PLAIN_TYPE = 'Content-Type: text/plain; charset=utf-8';
}
