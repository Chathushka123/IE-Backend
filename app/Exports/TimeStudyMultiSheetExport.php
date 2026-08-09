<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimeStudyMultiSheetExport implements WithMultipleSheets
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            new TimeStudyExport($this->filters),
            new TimeStudyLapsExport($this->filters),
        ];
    }
}
