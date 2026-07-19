<?php

namespace App\Exports;

use App\Operation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        // NOTE: the eager-load string here was previously 'operation.operationCategory', which
        // does not exist as a relation on the (old) Operation model — a pre-existing bug that
        // would throw at query time. Fixed here to 'baseOperation.category', matching the
        // renamed relation names (Operation::baseOperation() -> BaseOperation::category()).
        return Operation::with([
            'productCategory.productGroup',
            'baseOperation.category',
            'machineType.category',
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
            'Machine Category',
            'Machine Type',
            'Description',
            'Code',
            'Grade',
            'Sequence No',
            'SMV',
            'Active',
        ];
    }

    public function map($grading): array
    {
        return [
            optional(optional($grading->productCategory)->productGroup)->name,
            optional($grading->productCategory)->name,
            optional(optional($grading->baseOperation)->category)->name,
            optional($grading->baseOperation)->code,
            optional($grading->baseOperation)->name,
            optional(optional($grading->machineType)->category)->name,
            optional($grading->machineType)->name,
            $grading->description,
            $grading->code,
            optional($grading->grade)->name,
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
