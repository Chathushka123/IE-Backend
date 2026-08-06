<?php

namespace App\Exports;

use App\TimeStudy;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TimeStudyExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return TimeStudy::with([
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
            ->when($this->filters['study_date_from'] ?? null, fn ($q, $v) => $q->whereDate('study_date', '>=', $v))
            ->when($this->filters['study_date_to'] ?? null, fn ($q, $v) => $q->whereDate('study_date', '<=', $v))
            ->when($this->filters['time_study_types'] ?? null, fn ($q, $v) => $q->whereIn('time_study_type', (array) $v))
            ->when($this->filters['operation_ids'] ?? null, fn ($q, $v) => $q->whereIn('operation_id', (array) $v))
            ->when($this->filters['product_category_ids'] ?? null, fn ($q, $v) => $q->whereIn('product_category_id', (array) $v))
            ->when($this->filters['machine_type_ids'] ?? null, fn ($q, $v) => $q->whereIn('machine_type_id', (array) $v))
            ->when($this->filters['employee_ids'] ?? null, fn ($q, $v) => $q->whereIn('employee_id', (array) $v))
            ->when($this->filters['product_ids'] ?? null, fn ($q, $v) => $q->whereIn('product_id', (array) $v))
            ->when($this->filters['factory_ids'] ?? null, fn ($q, $v) => $q->whereIn('factory_id', (array) $v))
            ->orderByDesc('study_date')
            ->get();
    }

    public function headings(): array
    {
        return [
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
}
