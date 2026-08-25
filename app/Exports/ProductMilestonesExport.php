<?php

namespace App\Exports;

use App\Http\Repositories\ProductMilestoneRepository;
use App\ProductMilestone;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductMilestonesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    /** Columns C onward are the 18 date fields (A=Product, B=Planned Quantity). */
    const FIRST_DATE_COLUMN_INDEX = 3;

    private array $filters;

    /** @param array $filters Optional export filters from the Export Filters dialog — empty means "every milestone record". */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = ProductMilestone::with(['product']);

        ProductMilestoneRepository::applyExportFilters($query, $this->filters);

        return $query->get();
    }

    public function headings(): array
    {
        return array_merge(['Product', 'Planned Quantity'], array_keys(ProductMilestoneRepository::DATE_FIELDS));
    }

    public function map($milestone): array
    {
        $row = [
            optional($milestone->product)->name,
            $milestone->planned_quantity,
        ];

        foreach (ProductMilestoneRepository::DATE_FIELDS as $column) {
            $row[] = $this->toExcelDate($milestone->$column);
        }

        return $row;
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

    /** Every date column renders as a real Excel date rather than text. */
    public function columnFormats(): array
    {
        $formats = [];
        $count = count(ProductMilestoneRepository::DATE_FIELDS);
        for ($i = 0; $i < $count; $i++) {
            $columnLetter = $this->columnLetter(self::FIRST_DATE_COLUMN_INDEX + $i);
            $formats[$columnLetter] = NumberFormat::FORMAT_DATE_YYYYMMDD;
        }
        return $formats;
    }

    private function toExcelDate($date)
    {
        return $date ? ExcelDate::dateTimeToExcel($date) : null;
    }

    /** 1-indexed column number -> spreadsheet column letter (A, B, ..., Z, AA, ...). */
    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }
}
