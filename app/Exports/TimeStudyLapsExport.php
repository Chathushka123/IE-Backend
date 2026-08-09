<?php

namespace App\Exports;

use App\TimeStudyLap;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Second sheet of the Time Study workbook — every lap belonging to whichever studies
 * TimeStudyExport::filteredQuery() resolves for the same filter set, so both sheets
 * always describe the same set of studies.
 */
class TimeStudyLapsExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnWidths
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $studyIds = TimeStudyExport::filteredQuery($this->filters)->pluck('id');

        return TimeStudyLap::whereIn('time_study_id', $studyIds)
            ->with(['timeStudy.employee', 'timeStudy.operation'])
            ->orderBy('time_study_id')
            ->orderBy('lap_no')
            ->get();
    }

    public function title(): string
    {
        return 'Lap Details';
    }

    public function headings(): array
    {
        return [
            'Time Study ID',
            'Study Date',
            'Operator No',
            'Operator Name',
            'Operation',
            'Lap No',
            'Duration (sec)',
            'Down Time (sec)',
            'Net Time (sec)',
        ];
    }

    public function map($lap): array
    {
        $study = $lap->timeStudy;
        $employee = optional($study)->employee;

        return [
            $lap->time_study_id,
            optional(optional($study)->study_date)->format('Y-m-d'),
            optional($employee)->employee_no,
            optional($employee)->full_name ?: trim(optional($employee)->first_name . ' ' . optional($employee)->last_name),
            optional(optional($study)->operation)->description,
            $lap->lap_no,
            $this->msToSec($lap->duration_ms),
            $this->msToSec($lap->down_time_ms),
            $this->msToSec($lap->net_ms),
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
            'A' => 14, // Time Study ID
            'B' => 12, // Study Date
            'C' => 14, // Operator No
            'D' => 22, // Operator Name
            'E' => 30, // Operation
            'F' => 10, // Lap No
            'G' => 14, // Duration (sec)
            'H' => 14, // Down Time (sec)
            'I' => 14, // Net Time (sec)
        ];
    }
}
