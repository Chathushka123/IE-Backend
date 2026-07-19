<?php

namespace App\Exports\Sheets;

use App\OperationSkill;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationSkillMappingSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return OperationSkill::with([
            'operation.baseOperation.category',
            'operation.productCategory',
            'operation.machineType',
            'softSkill',
        ])
            ->orderBy('operation_id')
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
        $baseOperation = optional($row->operation)->baseOperation;

        return [
            optional($baseOperation)->code,
            optional($baseOperation)->name,
            optional(optional($baseOperation)->category)->code,
            optional(optional($baseOperation)->category)->name,
            optional(optional($row->operation)->productCategory)->name,
            optional(optional($row->operation)->machineType)->name,
            optional($row->softSkill)->code,
            optional($row->softSkill)->name,
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
