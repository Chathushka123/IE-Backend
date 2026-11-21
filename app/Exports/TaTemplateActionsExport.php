<?php

namespace App\Exports;

use App\Http\Repositories\TaTemplateActionRepository;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The standard T&A list as a sheet. The headings are the import's contract (the frontend's header map
 * mirrors them), so a re-export → edit → re-import round trip works.
 */
class TaTemplateActionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return TaTemplateActionRepository::listAll();
    }

    public function headings(): array
    {
        return ['Phase', 'Sequence', 'Action', 'Designation', 'Process Time', 'Active'];
    }

    public function map($action): array
    {
        return [
            $action->phase,
            $action->sequence,
            $action->name,
            optional($action->designation)->name,
            $action->process_time,
            $action->is_active ? 'Yes' : 'No',
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
