<?php

namespace App\Exports;

use App\OperationGrading;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationGradingsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return OperationGrading::with([
            'productCategory.productGroup',
            'operation.operationCategory',
            'grade',
        ])
            ->orderBy('product_category_id')
            ->orderBy('sequence_no')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Product Group',
            'Product Category',
            'Operation Category',
            'Operation Code',
            'Operation',
            'Grade',
            'Sequence No',
            'SMV',
            'Active',
        ];
    }

    public function map($grading): array
    {
        return [
            optional(optional($grading->productCategory)->productGroup)->description,
            optional($grading->productCategory)->description,
            optional(optional($grading->operation)->operationCategory)->description,
            optional($grading->operation)->code,
            optional($grading->operation)->description,
            optional($grading->grade)->description,
            $grading->sequence_no,
            $grading->smv,
            $grading->is_active ? 'Yes' : 'No',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }
}
