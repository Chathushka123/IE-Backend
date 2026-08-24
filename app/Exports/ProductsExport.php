<?php

namespace App\Exports;

use App\Customer;
use App\Factory;
use App\Http\Repositories\ProductRepository;
use App\Product;
use App\ProductCategory;
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

class ProductsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithColumnFormatting
{
    /** Extra blank rows below the data that still get dropdown validation, for adding new products. */
    const BUFFER_ROWS = 500;

    /** Column holding the only native Excel date: Customer Requested Delivery Date. */
    const DATE_COLUMNS = ['I'];

    private array $filters;

    /** @param array $filters Optional export filters from the Export Filters dialog — see applyFilters(). Empty means "every product" (current behavior). */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Product::with(['productCategory', 'customer', 'season', 'factories']);

        ProductRepository::applyExportFilters($query, $this->filters);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Style Code',
            'Style Description',
            'Product Category',
            'Customer',
            'Season',
            'Colors',
            'Sizes',
            'Customer Requested Delivery Date',
            'Planned Efficiency %',
            'Active',
            'Factories',
        ];
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->style_code,
            $product->style_description,
            optional($product->productCategory)->name,
            optional($product->customer)->description,
            optional($product->season)->name,
            $product->colors ? implode(', ', $product->colors) : null,
            $product->sizes ? implode(', ', $product->sizes) : null,
            $this->toExcelDate($product->customer_requested_delivery_date),
            $product->planned_efficiency_pct,
            $product->is_active ? 'Yes' : 'No',
            $product->factories->isNotEmpty() ? $product->factories->pluck('name')->implode(', ') : null,
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

    /** Renders Customer Requested Delivery Date as a real Excel date (see columnFormats()) rather than text. */
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
     */
    private function addDataValidation(Worksheet $sheet): void
    {
        $lastRow = max($sheet->getHighestRow(), 1) + self::BUFFER_ROWS;
        $ranges = $this->buildListsSheet($sheet->getParent());

        $this->applyInlineList($sheet, 'K', $lastRow, 'Yes,No');

        $this->applyRangeList($sheet, 'D', $lastRow, $ranges['category'], 'Product Category', 'Pick from the list — must match an existing product category exactly.');
        $this->applyRangeList($sheet, 'E', $lastRow, $ranges['customer'], 'Customer', 'Pick from the list — must match an existing customer exactly.');

        // Factories is comma-separated (a product can belong to several), so it can't use
        // Excel's single-value list validation like the columns above — this only attaches
        // an input prompt pointing at the same hidden Lists sheet for reference.
        $this->applyPrompt(
            $sheet,
            'L',
            $lastRow,
            'Factories',
            'Comma-separated, must match factory names exactly (see the hidden Lists sheet, column C). Leave blank to leave a product\'s existing factories unchanged on update.'
        );

        foreach (self::DATE_COLUMNS as $column) {
            $this->applyDateValidation($sheet, $column, $lastRow);
        }
    }

    /**
     * Populates a hidden "Lists" sheet with active master data so the dropdowns above
     * can reference a cell range — Excel's inline list formula can't hold an arbitrary,
     * growing number of values.
     */
    private function buildListsSheet(Spreadsheet $spreadsheet): array
    {
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Lists');

        $columns = [
            'A' => ProductCategory::where('is_active', true)->orderBy('name')->pluck('name'),
            'B' => Customer::where('is_active', true)->orderBy('description')->pluck('description'),
            'C' => Factory::where('is_active', true)->orderBy('name')->pluck('name'),
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
            'customer' => "Lists!\$B\$2:\$B\${$lastRows['B']}",
            'factory' => "Lists!\$C\$2:\$C\${$lastRows['C']}",
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

    /**
     * Attaches an input-message-only hint to a column — no restriction on what can be
     * typed, just a tooltip shown when the cell is selected. Used for Factories, which
     * can't take Excel's single-value list validation since one cell holds several
     * comma-separated names.
     */
    private function applyPrompt(Worksheet $sheet, string $column, int $lastRow, string $promptTitle, string $prompt): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_NONE);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setPromptTitle($promptTitle);
        $validation->setPrompt($prompt);

        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getCell("{$column}{$row}")->setDataValidation(clone $validation);
        }
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
     * Restricts the Customer Requested Delivery Date cell to a real, in-range date.
     * Pairs with columnFormats(), which renders the underlying value as yyyy-mm-dd —
     * Excel's Date validation only works against genuine date values, not text, which
     * is why map() exports this as an Excel date serial, not a string.
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
}
