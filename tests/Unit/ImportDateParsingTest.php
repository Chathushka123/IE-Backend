<?php

namespace Tests\Unit;

use App\Http\Repositories\Utilities;
use Exception;
use PHPUnit\Framework\TestCase;

class ImportDateParsingTest extends TestCase
{
    /** @dataProvider validDates */
    public function test_it_normalises_valid_dates_to_ymd($input, $expected)
    {
        $this->assertSame($expected, Utilities::parseImportDate($input, 'Actual Date'));
    }

    public function validDates(): array
    {
        return [
            'iso' => ['2026-11-23', '2026-11-23'],
            'iso with unpadded parts' => ['2026-1-5', '2026-01-05'],
            'day-first dashes (the value that broke MySQL)' => ['23-11-2026', '2026-11-23'],
            'day-first slashes' => ['23/11/2026', '2026-11-23'],
            'day-first dots' => ['23.11.2026', '2026-11-23'],
            'iso with time' => ['2026-11-23 00:00:00', '2026-11-23'],
            'padded with spaces' => ['  2026-11-23  ', '2026-11-23'],
            'datetime object' => [new \DateTimeImmutable('2026-11-23 10:00:00'), '2026-11-23'],
        ];
    }

    /** @dataProvider blankValues */
    public function test_it_treats_blank_as_no_value($input)
    {
        $this->assertNull(Utilities::parseImportDate($input, 'Actual Date'));
    }

    public function blankValues(): array
    {
        return [[null], [''], ['   ']];
    }

    /** @dataProvider invalidDates */
    public function test_it_rejects_invalid_dates_with_an_actionable_message($input)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/^Actual Date '.*' isn't a valid date\. Use yyyy-mm-dd/");

        Utilities::parseImportDate($input, 'Actual Date');
    }

    public function invalidDates(): array
    {
        return [['tomorrow'], ['31-02-2026'], ['2026-13-01'], ['11/23/2026'], ['23-11-26'], ['20261123']];
    }

    public function test_import_row_number_prefers_the_sheet_row_the_frontend_sent()
    {
        $this->assertSame(9, Utilities::importRowNumber(['_row' => 9], 0));
        $this->assertSame(2, Utilities::importRowNumber([], 0));
    }
}
