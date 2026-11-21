<?php

namespace App\Exports;

use App\Http\Repositories\StyleTaActionRepository;
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

/**
 * Style T&A actions as a sheet — for one style, or for the Tracker's current filters. The headings
 * are the import's contract. Style, Style Code, Employee and Status are informational on import:
 * the style is matched by name, the employee by Employee No, and status is derived from the dates.
 */
class StyleTaActionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private const STATUS_LABELS = [
        'done' => 'Done',
        'overdue' => 'Overdue',
        'due_soon' => 'Due soon',
        'upcoming' => 'Upcoming',
        'not_scheduled' => 'Not scheduled',
    ];

    private array $filters;
    private string $today;

    public function __construct(array $filters = [])
    {
        $this->today = StyleTaActionRepository::resolveToday($filters['today'] ?? null);
        $this->filters = $filters;
    }

    public function collection()
    {
        // Grouped by style, then the plan's own order — not the Tracker's urgency order.
        return StyleTaActionRepository::trackerQuery($this->filters, $this->today)
            ->reorder()
            ->orderBy('style_id')
            ->get()
            ->sortBy(fn ($a) => [$a->style_id, \App\TaTemplateAction::phaseRank($a->phase), $a->sequence, $a->id])
            ->values();
    }

    public function headings(): array
    {
        return ['Style', 'Style Code', 'Phase', 'Sequence', 'Action', 'Designation', 'Employee No', 'Employee',
            'Process Time', 'Planned Date', 'Actual Date', 'Status', 'Remarks'];
    }

    public function map($action): array
    {
        $employee = $action->employee;

        return [
            optional($action->style)->name,
            optional($action->style)->style_code,
            $action->phase,
            $action->sequence,
            $action->name,
            optional($action->designation)->name,
            optional($employee)->employee_no,
            $employee ? ($employee->full_name ?: trim($employee->first_name.' '.$employee->last_name)) : null,
            $action->process_time,
            $action->planned_date ? ExcelDate::dateTimeToExcel($action->planned_date) : null,
            $action->actual_date ? ExcelDate::dateTimeToExcel($action->actual_date) : null,
            self::STATUS_LABELS[StyleTaActionRepository::statusFor($action, $this->today)],
            $action->remarks,
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

    /** Planned Date (J) and Actual Date (K) render as real Excel dates, not text. */
    public function columnFormats(): array
    {
        return ['J' => NumberFormat::FORMAT_DATE_YYYYMMDD, 'K' => NumberFormat::FORMAT_DATE_YYYYMMDD];
    }
}
