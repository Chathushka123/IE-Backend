<?php

namespace App\Exports;

use App\Country;
use App\Department;
use App\Designation;
use App\Employee;
use App\Http\Repositories\EmployeeRepository;
use App\ManagementHierarchy;
use App\Factory;
use App\Team;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithColumnFormatting
{
    /** Extra blank rows below the data that still get dropdown validation, for adding new employees. */
    const BUFFER_ROWS = 500;

    /** Columns holding native Excel dates: Birthday, Joining/Leaving/Confirmation Date. */
    const DATE_COLUMNS = ['G', 'W', 'X', 'Y'];

    private array $filters;

    /** @param array $filters Optional export filters from the Export Filters dialog — see applyFilters(). Empty means "everyone" (current behavior). */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Employee::with([
            'managementHierarchy',
            'factory',
            'department',
            'designation',
            'country',
            'reportingManager',
            'team',
            'baseTeam',
        ]);

        EmployeeRepository::applyExportFilters($query, $this->filters);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Employee No',
            'NIC No / Passport No / Driving Licence No',
            'Full Name',
            'First Name',
            'Last Name',
            'Gender',
            'Birthday',
            'Email Address',
            'Contact Country Code',
            'Contact No',
            'Marital Status',
            'Street Name',
            'House No',
            'Address Line',
            'City / State / Province',
            'Postal Code',
            'Country',
            'Management Hierarchy',
            'Department',
            'Designation',
            'Employee Category',
            'Employment Type',
            'Joining Date',
            'Leaving Date',
            'Confirmation Date',
            'Reporting Manager',
            'Team',
            'Base Team',
            'Employee Status',
            'Factory',
            'Nationality',
            'Religion',
            'EPF No',
            // Read-only/computed — not part of IMPORT_HEADER_MAP on the frontend, so
            // re-uploading an exported file leaves this column ignored rather than
            // trying to set it directly.
            'Retirement Date',
        ];
    }

    public function map($employee): array
    {
        [$contactCountryCode, $contactNumber] = $this->splitContactNo($employee->contact_no);

        return [
            $employee->employee_no,
            $employee->identification_no,
            $employee->full_name,
            $employee->first_name,
            $employee->last_name,
            $employee->gender,
            $this->toExcelDate($employee->birthday),
            $employee->email_address,
            $contactCountryCode,
            $contactNumber,
            $employee->marital_status,
            $employee->street_name,
            $employee->house_no,
            $employee->address_line,
            $employee->city_or_province,
            $employee->postal_code,
            optional($employee->country)->name,
            optional($employee->managementHierarchy)->name,
            optional($employee->department)->name,
            optional($employee->designation)->name,
            $employee->employee_category,
            $employee->employment_type,
            $this->toExcelDate($employee->joining_date),
            $this->toExcelDate($employee->leaving_date),
            $this->toExcelDate($employee->confirmation_date),
            // The importer matches Reporting Manager by Employee No first — export that
            // instead of the name so the value always round-trips unambiguously, and so
            // it matches the dropdown list built in addDataValidation().
            optional($employee->reportingManager)->employee_no,
            optional($employee->team)->name,
            optional($employee->baseTeam)->name,
            $employee->employee_status,
            optional($employee->factory)->name,
            $employee->nationality,
            $employee->religion,
            $employee->epf_no,
            $this->toExcelDate($employee->retirement_date ? \Carbon\Carbon::parse($employee->retirement_date) : null),
        ];
    }

    /**
     * Splits a stored "<country code>-<number>" contact_no (e.g. "+94-771234567") into its
     * two export columns, mirroring the frontend's splitContactNo() in EmployeeFormModal.tsx.
     */
    private function splitContactNo(?string $contactNo): array
    {
        if (!$contactNo) {
            return [null, null];
        }
        $dashIndex = strpos($contactNo, '-');
        if ($dashIndex === false) {
            return [null, $contactNo];
        }
        return [substr($contactNo, 0, $dashIndex), substr($contactNo, $dashIndex + 1)];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }

    /** Renders Birthday/Joining/Leaving/Confirmation Date as real Excel dates (see columnFormats()) rather than text. */
    public function columnFormats(): array
    {
        return array_merge(
            array_fill_keys(self::DATE_COLUMNS, NumberFormat::FORMAT_DATE_YYYYMMDD),
            // Retirement Date is read-only/computed (see headings()) — formatted for display
            // only, deliberately not in DATE_COLUMNS since that also drives input validation
            // for the editable date columns below.
            ['AH' => NumberFormat::FORMAT_DATE_YYYYMMDD]
        );
    }

    private function toExcelDate($date)
    {
        return $date ? ExcelDate::dateTimeToExcel($date) : null;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->addDataValidation($event->sheet->getDelegate());
            },
        ];
    }

    /**
     * Adds Excel dropdown validation to every lookup/enum column so manual edits (and
     * new rows appended for import) can't drift from what the importer can resolve.
     * Fixed enums use an inline list; master-data lookups reference a hidden sheet
     * since their values are open-ended and can't fit Excel's inline list limit.
     */
    private function addDataValidation(Worksheet $sheet): void
    {
        $lastRow = max($sheet->getHighestRow(), 1) + self::BUFFER_ROWS;
        $ranges = $this->buildListsSheet($sheet->getParent());

        $this->applyInlineList($sheet, 'F', $lastRow, 'Male,Female,Other');
        $this->applyInlineList($sheet, 'K', $lastRow, 'Single,Married,Divorced,Other');
        $this->applyInlineList($sheet, 'U', $lastRow, 'Direct,Indirect');
        $this->applyInlineList($sheet, 'V', $lastRow, 'Permanent,Contract,Casual');
        $this->applyInlineList($sheet, 'AC', $lastRow, 'Active,Resigned,Terminated');

        $this->applyRangeList($sheet, 'Q', $lastRow, $ranges['country'], 'Country', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'R', $lastRow, $ranges['management_hierarchy'], 'Management Hierarchy', 'Pick from the list — must match an existing management hierarchy exactly.');
        $this->applyRangeList($sheet, 'S', $lastRow, $ranges['department'], 'Department', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'T', $lastRow, $ranges['designation'], 'Designation', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'Z', $lastRow, $ranges['employee'], 'Reporting Manager', "Enter the manager's Employee No, or leave blank.");
        $this->applyRangeList($sheet, 'AA', $lastRow, $ranges['team'], 'Team', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'AB', $lastRow, $ranges['team'], 'Base Team', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'AD', $lastRow, $ranges['factory'], 'Factory', 'Pick from the list — must match an existing factory exactly.');

        foreach (self::DATE_COLUMNS as $column) {
            $this->applyDateValidation($sheet, $column, $lastRow);
        }
        $this->applyEmailValidation($sheet, 'H', $lastRow);
    }

    /**
     * Populates a hidden "Lists" sheet with active master data so the dropdowns above
     * can reference a cell range — Excel's inline list formula can't hold an arbitrary,
     * growing number of values. Returns the last populated row per column.
     */
    private function buildListsSheet(Spreadsheet $spreadsheet): array
    {
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Lists');

        $columns = [
            'A' => ManagementHierarchy::where('is_active', true)->orderBy('seq_no')->orderBy('name')->pluck('name'),
            'B' => Department::where('is_active', true)->orderBy('name')->pluck('name'),
            'C' => Designation::where('is_active', true)->orderBy('name')->pluck('name'),
            'D' => Employee::where('employee_status', 'Active')->orderBy('employee_no')->pluck('employee_no'),
            'E' => Team::where('is_active', true)->orderBy('name')->pluck('name'),
            'F' => Factory::where('is_active', true)->orderBy('name')->pluck('name'),
            'G' => Country::where('is_active', true)->orderBy('name')->pluck('name'),
        ];

        $lastRows = [];
        foreach ($columns as $column => $values) {
            $row = 2;
            foreach ($values as $value) {
                $listSheet->setCellValue("{$column}{$row}", $value);
                $row++;
            }
            $lastRows[$column] = max($row - 1, 2);
        }

        $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return [
            'management_hierarchy' => "Lists!\$A\$2:\$A\${$lastRows['A']}",
            'department' => "Lists!\$B\$2:\$B\${$lastRows['B']}",
            'designation' => "Lists!\$C\$2:\$C\${$lastRows['C']}",
            'employee' => "Lists!\$D\$2:\$D\${$lastRows['D']}",
            'team' => "Lists!\$E\$2:\$E\${$lastRows['E']}",
            'factory' => "Lists!\$F\$2:\$F\${$lastRows['F']}",
            'country' => "Lists!\$G\$2:\$G\${$lastRows['G']}",
        ];
    }

    private function applyInlineList(Worksheet $sheet, string $column, int $lastRow, string $commaSeparatedValues): void
    {
        $this->applyValidation($sheet, $column, $lastRow, '"' . $commaSeparatedValues . '"', null, null);
    }

    private function applyRangeList(Worksheet $sheet, string $column, int $lastRow, string $rangeFormula, string $promptTitle, string $prompt): void
    {
        // Range references must NOT have a leading '=' (unlike a normal cell formula) —
        // PhpSpreadsheet writes this straight into the OOXML <formula1> element as-is.
        $this->applyValidation($sheet, $column, $lastRow, $rangeFormula, $promptTitle, $prompt);
    }

    private function applyValidation(Worksheet $sheet, string $column, int $lastRow, string $formula1, ?string $promptTitle, ?string $prompt): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        // NOTE: PhpSpreadsheet negates this flag when writing the XLSX's `showDropDown`
        // XML attribute (that attribute means "suppress the arrow" in the OOXML spec,
        // despite its name) — setShowDropDown(true) here is what makes Excel render the
        // in-cell dropdown arrow. Do not "fix" this to false.
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula1);
        $validation->setErrorTitle('Invalid value');
        $validation->setError('Please pick a value from the dropdown list.');
        if ($promptTitle !== null) {
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle($promptTitle);
            $validation->setPrompt($prompt);
        }

        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getCell("{$column}{$row}")->setDataValidation(clone $validation);
        }
    }

    /**
     * Restricts a Birthday/Joining/Leaving/Confirmation Date cell to a real, in-range
     * date. Pairs with columnFormats(), which renders the underlying value as
     * yyyy-mm-dd — Excel's Date validation only works against genuine date values,
     * not text, which is why map() exports these as Excel date serials, not strings.
     */
    private function applyDateValidation(Worksheet $sheet, string $column, int $lastRow): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_DATE);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setFormula1('DATE(1900,1,1)');
        $validation->setFormula2('DATE(2100,12,31)');
        $validation->setErrorTitle('Invalid date');
        $validation->setError('Enter a valid date (formatted as yyyy-mm-dd).');
        $validation->setPromptTitle('Date');
        $validation->setPrompt('Enter a date in yyyy-mm-dd format.');

        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getCell("{$column}{$row}")->setDataValidation(clone $validation);
        }
    }

    /**
     * Basic "does this look like an email" check (contains exactly one @, with
     * something before it and a . with something after it, following the @, and no
     * spaces). Excel's classic formula language has no regex, and the formula must
     * reference its own cell, so — unlike the other validations — this can't be a
     * single cloned template; each row gets its own formula string.
     */
    private function applyEmailValidation(Worksheet $sheet, string $column, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $cell = "{$column}{$row}";
            $formula = "IFERROR(AND(" .
                "ISERROR(FIND(\" \",{$cell}))," .
                "FIND(\"@\",{$cell})>1," .
                "FIND(\".\",{$cell},FIND(\"@\",{$cell})+1)>FIND(\"@\",{$cell})+1," .
                "LEN({$cell})>FIND(\".\",{$cell},FIND(\"@\",{$cell})+1)," .
                "RIGHT({$cell},1)<>\".\"," .
                "LEN({$cell})-LEN(SUBSTITUTE({$cell},\"@\",\"\"))=1" .
                "),FALSE)";

            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_CUSTOM);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1($formula);
            $validation->setErrorTitle('Invalid email');
            $validation->setError('Enter a valid email address (e.g. name@example.com), or leave blank.');
            $validation->setPromptTitle('Email Address');
            $validation->setPrompt('Enter a valid email address, or leave blank.');

            $sheet->getCell($cell)->setDataValidation($validation);
        }
    }
}
