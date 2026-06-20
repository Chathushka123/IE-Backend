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
        return OperationSkill::with('operation.category', 'skill')
            ->orderBy('operation_id')
            ->get();
    }

    public function title(): string
    {
        return 'Operation-Skill Mapping';
    }

    public function headings(): array
    {
        return [
            'Operation Code',
            'Operation Description',
            'Category Code',
            'Category Description',
            'Skill Code',
            'Skill Description',
            'Active',
        ];
    }

    public function map($row): array
    {
        return [
            optional($row->operation)->code,
            optional($row->operation)->description,
            optional($row->operation->category)->code,
            optional($row->operation->category)->description,
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
