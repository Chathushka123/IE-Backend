<?php

namespace App\Exports\Sheets;

use App\BaseOperation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BaseOperationsSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return BaseOperation::with('category')->orderBy('base_operation_category_id')->orderBy('name')->get();
    }

    public function title(): string
    {
        return 'Operations';
    }

    public function headings(): array
    {
        return ['Operation Code', 'Operation Description', 'Category Code', 'Category Description', 'Active'];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            optional($row->category)->code,
            optional($row->category)->name,
            $row->is_active ? 'Yes' : 'No',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ],
        ];
    }
}
