<?php

namespace App\Exports;

use App\Exports\Sheets\OperationSkillMappingSheet;
use App\Exports\Sheets\OperationsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OperationsExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new OperationsSheet(),
            new OperationSkillMappingSheet(),
        ];
    }
}
