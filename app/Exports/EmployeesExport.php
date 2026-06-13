<?php

namespace App\Exports;

use App\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return Employee::with([
            'category',
            'department',
            'designation',
            'reportingManager',
            'productionLine',
            'baseLine',
        ])->get();
    }

    public function headings(): array
    {
        return [
            'Employee No',
            'NIC No',
            'Full Name',
            'First Name',
            'Last Name',
            'Gender',
            'Birthday',
            'Email Address',
            'Contact No',
            'Address',
            'Marital Status',
            'Category',
            'Department',
            'Designation',
            'Joining Date',
            'Leaving Date',
            'Confirmation Date',
            'Employment Type',
            'Reporting Manager',
            'Production Line',
            'Base Line',
            'Employee Status',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->employee_no,
            $employee->nic_no,
            $employee->full_name,
            $employee->first_name,
            $employee->last_name,
            $employee->gender,
            optional($employee->birthday)->format('Y-m-d'),
            $employee->email_address,
            $employee->contact_no,
            $employee->address,
            $employee->marital_status,
            optional($employee->category)->description,
            optional($employee->department)->description,
            optional($employee->designation)->description,
            optional($employee->joining_date)->format('Y-m-d'),
            optional($employee->leaving_date)->format('Y-m-d'),
            optional($employee->confirmation_date)->format('Y-m-d'),
            $employee->employment_type,
            optional($employee->reportingManager)->full_name,
            optional($employee->productionLine)->description,
            optional($employee->baseLine)->description,
            $employee->employee_status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }
}
