<?php

namespace App\Exports;

use App\TimeStudy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimeStudyExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnWidths
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * The report's filter set applied to a bare TimeStudy query, with no eager-loading —
     * shared with TimeStudyLapsExport so both sheets of the workbook scope to exactly the
     * same set of studies without duplicating the filter chain.
     */
    public static function filteredQuery(array $filters): Builder
    {
        return TimeStudy::query()
            ->when($filters['study_date_from'] ?? null, fn ($q, $v) => $q->whereDate('study_date', '>=', $v))
            ->when($filters['study_date_to'] ?? null, fn ($q, $v) => $q->whereDate('study_date', '<=', $v))
            ->when($filters['time_study_types'] ?? null, fn ($q, $v) => $q->whereIn('time_study_type', (array) $v))
            ->when($filters['operation_ids'] ?? null, fn ($q, $v) => $q->whereIn('operation_id', (array) $v))
            ->when($filters['product_category_ids'] ?? null, fn ($q, $v) => $q->whereIn('product_category_id', (array) $v))
            ->when($filters['machine_type_ids'] ?? null, fn ($q, $v) => $q->whereIn('machine_type_id', (array) $v))
            ->when($filters['employee_ids'] ?? null, fn ($q, $v) => $q->whereIn('employee_id', (array) $v))
            ->when($filters['product_ids'] ?? null, fn ($q, $v) => $q->whereIn('product_id', (array) $v))
            ->when($filters['factory_ids'] ?? null, fn ($q, $v) => $q->whereIn('factory_id', (array) $v))
            ->when($filters['created_by_ids'] ?? null, fn ($q, $v) => $q->whereIn('created_by_id', (array) $v))
            ->when($filters['ids'] ?? null, fn ($q, $v) => $q->whereIn('id', (array) $v));
    }

    public function collection()
    {
        return self::filteredQuery($this->filters)
            ->with([
                'factory',
                'operation.baseOperation',
                'product',
                'employee.department',
                'productCategory',
                'machineType',
                'softSkills',
                'laps',
                'downtimes.reason',
            ])
            ->orderByDesc('study_date')
            ->get();
    }

    public function title(): string
    {
        return 'Time Studies';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Time Study Type',
            'Factory',
            'Operation Code',
            'Operation',
            'Base Operation',
            'Product Category',
            'Machine Type',
            'Style (Product)',
            'Style Code',
            'Operator No',
            'Operator Name',
            'Department',
            'Skills',
            'SMV (min)',
            'SMV (sec)',
            'Productive Time (sec)',
            'Down Time (sec)',
            'Hold Time (sec)',
            'Total Cycle (sec)',
            'Avg Cycle Time (sec)',
            'Fastest Cycle (sec)',
            'Slowest Cycle (sec)',
            'Efficiency (%)',
            'Lap Count',
            'Down Time Entries',
            'Recorded At',
        ];
    }

    public function map($study): array
    {
        return [
            $study->id,
            optional($study->study_date)->format('Y-m-d'),
            $study->time_study_type === 'interview_training' ? 'Interview & Training' : 'Production Floor',
            optional($study->factory)->name,
            optional($study->operation)->code,
            optional($study->operation)->description,
            optional(optional($study->operation)->baseOperation)->name,
            optional($study->productCategory)->name,
            optional($study->machineType)->name,
            optional($study->product)->name,
            optional($study->product)->style_code,
            optional($study->employee)->employee_no,
            optional($study->employee)->full_name
                ?: trim(optional($study->employee)->first_name . ' ' . optional($study->employee)->last_name),
            optional(optional($study->employee)->department)->name,
            $study->softSkills->pluck('name')->implode(', '),
            $study->smv,
            $study->smv !== null ? round($study->smv * 60, 2) : null,
            $this->msToSec($study->total_productive_ms),
            $this->msToSec($study->total_down_time_ms),
            $this->msToSec($study->total_hold_ms),
            $this->msToSec($study->total_cycle_ms),
            $this->msToSec($study->avg_cycle_ms),
            $this->msToSec($study->fastest_cycle_ms),
            $this->msToSec($study->slowest_cycle_ms),
            $study->efficiency_pct,
            $study->laps->count(),
            $study->downtimes->map(fn ($d) => optional($d->reason)->name)->filter()->implode(', '),
            optional($study->created_at)->format('Y-m-d H:i'),
        ];
    }

    private function msToSec($ms)
    {
        return $ms !== null ? round($ms / 1000, 2) : null;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '000080'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // ID
            'B' => 12,  // Date
            'C' => 20,  // Time Study Type
            'D' => 16,  // Factory
            'E' => 14,  // Operation Code
            'F' => 30,  // Operation
            'G' => 20,  // Base Operation
            'H' => 18,  // Product Category
            'I' => 16,  // Machine Type
            'J' => 22,  // Style (Product)
            'K' => 14,  // Style Code
            'L' => 14,  // Operator No
            'M' => 22,  // Operator Name
            'N' => 18,  // Department
            'O' => 25,  // Skills
            'P' => 12,  // SMV (min)
            'Q' => 12,  // SMV (sec)
            'R' => 16,  // Productive Time (sec)
            'S' => 14,  // Down Time (sec)
            'T' => 14,  // Hold Time (sec)
            'U' => 14,  // Total Cycle (sec)
            'V' => 16,  // Avg Cycle Time (sec)
            'W' => 14,  // Fastest Cycle (sec)
            'X' => 14,  // Slowest Cycle (sec)
            'Y' => 12,  // Efficiency (%)
            'Z' => 10,  // Lap Count
            'AA' => 25, // Down Time Entries
            'AB' => 16, // Recorded At
        ];
    }
}
