<?php

namespace App\Exports\Sheets;

use App\OperationGradingSkill;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationGradingSkillMappingSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return OperationGradingSkill::with([
            'operationGrading.operation.category',
            'operationGrading.productCategory',
            'operationGrading.machineType',
            'skill',
        ])
            ->orderBy('operation_grading_id')
            ->get();
    }

    public function title(): string
    {
        return 'Operation Grading-Skill Mapping';
    }

    public function headings(): array
    {
        return [
            'Operation Code',
            'Operation Description',
            'Category Code',
            'Category Description',
            'Product Category',
            'Machine Type',
            'Skill Code',
            'Skill Description',
            'Active',
        ];
    }

    public function map($row): array
    {
        $operation = optional($row->operationGrading)->operation;

        return [
            optional($operation)->code,
            optional($operation)->description,
            optional(optional($operation)->category)->code,
            optional(optional($operation)->category)->description,
            optional(optional($row->operationGrading)->productCategory)->description,
            optional(optional($row->operationGrading)->machineType)->description,
            optional($row->skill)->code,
            optional($row->skill)->description,
            $row->is_active ? 'Yes' : 'No',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '375623']],
            ],
        ];
    }
}
