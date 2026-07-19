<?php

namespace App\Exports;

use App\Exports\Sheets\OperationSkillMappingSheet;
use App\Exports\Sheets\BaseOperationsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BaseOperationsExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new BaseOperationsSheet(),
            new OperationSkillMappingSheet(),
        ];
    }
}
