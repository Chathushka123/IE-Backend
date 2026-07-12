<?php

namespace App\Exports;

use App\Department;
use App\Designation;
use App\Employee;
use App\EmployeeCategory;
use App\Factory;
use App\ProductionLine;
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
    const DATE_COLUMNS = ['G', 'O', 'P', 'Q'];

    public function collection()
    {
        return Employee::with([
            'category',
            'factory',
            'department',
            'designation',
            'reportingManager',
            'productionLine',
            'baseLine',
        ])->get();
    }

    public function headings(): array
    {
        return [
            'Employee No',
            'NIC No',
            'Full Name',
            'First Name',
            'Last Name',
            'Gender',
            'Birthday',
            'Email Address',
            'Contact No',
            'Address',
            'Marital Status',
            'Category',
            'Department',
            'Designation',
            'Joining Date',
            'Leaving Date',
            'Confirmation Date',
            'Employment Type',
            'Reporting Manager',
            'Production Line',
            'Base Line',
            'Employee Status',
            'Factory',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->employee_no,
            $employee->nic_no,
            $employee->full_name,
            $employee->first_name,
            $employee->last_name,
            $employee->gender,
            $this->toExcelDate($employee->birthday),
            $employee->email_address,
            $employee->contact_no,
            $employee->address,
            $employee->marital_status,
            optional($employee->category)->description,
            optional($employee->department)->description,
            optional($employee->designation)->description,
            $this->toExcelDate($employee->joining_date),
            $this->toExcelDate($employee->leaving_date),
            $this->toExcelDate($employee->confirmation_date),
            $employee->employment_type,
            // The importer matches Reporting Manager by Employee No first — export that
            // instead of the name so the value always round-trips unambiguously, and so
            // it matches the dropdown list built in addDataValidation().
            optional($employee->reportingManager)->employee_no,
            optional($employee->productionLine)->description,
            optional($employee->baseLine)->description,
            $employee->employee_status,
            optional($employee->factory)->description,
        ];
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
        return array_fill_keys(self::DATE_COLUMNS, NumberFormat::FORMAT_DATE_YYYYMMDD);
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
        $this->applyInlineList($sheet, 'R', $lastRow, 'Permanent,Contract,Casual');
        $this->applyInlineList($sheet, 'V', $lastRow, 'Active,Resigned,Terminated');

        $this->applyRangeList($sheet, 'L', $lastRow, $ranges['category'], 'Category', 'Pick from the list — must match an existing employee category exactly.');
        $this->applyRangeList($sheet, 'M', $lastRow, $ranges['department'], 'Department', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'N', $lastRow, $ranges['designation'], 'Designation', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'S', $lastRow, $ranges['employee'], 'Reporting Manager', "Enter the manager's Employee No, or leave blank.");
        $this->applyRangeList($sheet, 'T', $lastRow, $ranges['productionLine'], 'Production Line', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'U', $lastRow, $ranges['productionLine'], 'Base Line', 'Pick from the list, or leave blank.');
        $this->applyRangeList($sheet, 'W', $lastRow, $ranges['factory'], 'Factory', 'Pick from the list — must match an existing factory exactly.');

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
            'A' => EmployeeCategory::where('is_active', true)->orderBy('description')->pluck('description'),
            'B' => Department::where('is_active', true)->orderBy('description')->pluck('description'),
            'C' => Designation::where('is_active', true)->orderBy('description')->pluck('description'),
            'D' => Employee::where('employee_status', 'Active')->orderBy('employee_no')->pluck('employee_no'),
            'E' => ProductionLine::where('is_active', true)->orderBy('description')->pluck('description'),
            'F' => Factory::where('is_active', true)->orderBy('description')->pluck('description'),
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
            'category' => "Lists!\$A\$2:\$A\${$lastRows['A']}",
            'department' => "Lists!\$B\$2:\$B\${$lastRows['B']}",
            'designation' => "Lists!\$C\$2:\$C\${$lastRows['C']}",
            'employee' => "Lists!\$D\$2:\$D\${$lastRows['D']}",
            'productionLine' => "Lists!\$E\$2:\$E\${$lastRows['E']}",
            'factory' => "Lists!\$F\$2:\$F\${$lastRows['F']}",
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
