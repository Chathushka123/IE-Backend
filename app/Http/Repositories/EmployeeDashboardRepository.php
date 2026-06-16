<?php

namespace App\Http\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeDashboardRepository
{
    public static function getDashboardData(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::now()->subMonths(11)->startOfMonth();
        $to   = $toDate   ? Carbon::parse($toDate)->endOfDay()     : Carbon::now()->endOfMonth();

        return [
            'kpis'                  => self::getKpis(),
            'by_status'             => self::getByStatus(),
            'by_department'         => self::getByDimension('departments', 'department_id'),
            'by_category'           => self::getByDimension('employee_categories', 'category_id'),
            'by_designation'        => self::getByDesignation(),
            'by_employment_type'    => self::getByEnumField('employment_type'),
            'by_gender'             => self::getByEnumField('gender'),
            'by_production_line'    => self::getByDimension('production_lines', 'production_line_id'),
            'headcount_trend'       => self::getJoiningTrend($from, $to),
            'attrition_trend'       => self::getAttritionTrend($from, $to),
            'upcoming_anniversaries' => self::getUpcomingAnniversaries(),
        ];
    }

    private static function getKpis(): array
    {
        $total = DB::table('employees')->count();

        $active      = DB::table('employees')->where('employee_status', 'Active')->count();
        $resigned    = DB::table('employees')->where('employee_status', 'Resigned')->count();
        $terminated  = DB::table('employees')->where('employee_status', 'Terminated')->count();

        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;

        $newThisMonth = DB::table('employees')
            ->whereMonth('joining_date', $currentMonth)
            ->whereYear('joining_date', $currentYear)
            ->count();

        $pendingConfirmation = DB::table('employees')
            ->where('employee_status', 'Active')
            ->whereNotNull('joining_date')
            ->whereNull('confirmation_date')
            ->count();

        return [
            'total'                => $total,
            'active'               => $active,
            'resigned'             => $resigned,
            'terminated'           => $terminated,
            'active_pct'           => $total > 0 ? round($active / $total * 100, 1) : 0,
            'resigned_pct'         => $total > 0 ? round($resigned / $total * 100, 1) : 0,
            'terminated_pct'       => $total > 0 ? round($terminated / $total * 100, 1) : 0,
            'new_this_month'       => $newThisMonth,
            'pending_confirmation' => $pendingConfirmation,
        ];
    }

    private static function getByStatus(): array
    {
        return DB::table('employees')
            ->select('employee_status as label', DB::raw('COUNT(*) as count'))
            ->groupBy('employee_status')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getByDimension(string $table, string $fk): array
    {
        return DB::table('employees')
            ->join($table, "employees.{$fk}", '=', "{$table}.id")
            ->select("{$table}.description as label", DB::raw('COUNT(*) as count'))
            ->whereNotNull("employees.{$fk}")
            ->groupBy("{$table}.id", "{$table}.description")
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getByDesignation(): array
    {
        return DB::table('employees')
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('designations.description as label', DB::raw('COUNT(*) as count'))
            ->whereNotNull('employees.designation_id')
            ->groupBy('designations.id', 'designations.description')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private static function getByEnumField(string $field): array
    {
        return DB::table('employees')
            ->select("{$field} as label", DB::raw('COUNT(*) as count'))
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getJoiningTrend(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('employees')
            ->select(
                DB::raw("DATE_FORMAT(joining_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('joining_date')
            ->whereBetween('joining_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw("DATE_FORMAT(joining_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return self::fillMonthGaps($rows, $from, $to);
    }

    private static function getAttritionTrend(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('employees')
            ->select(
                DB::raw("DATE_FORMAT(leaving_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('leaving_date')
            ->whereBetween('leaving_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw("DATE_FORMAT(leaving_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return self::fillMonthGaps($rows, $from, $to);
    }

    private static function fillMonthGaps($rows, Carbon $from, Carbon $to): array
    {
        $result  = [];
        $cursor  = $from->copy()->startOfMonth();
        $end     = $to->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $result[] = [
                'month' => $key,
                'count' => isset($rows[$key]) ? (int) $rows[$key]->count : 0,
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    private static function getUpcomingAnniversaries(): array
    {
        $today          = Carbon::today();
        $thirtyDaysLater = Carbon::today()->addDays(30);

        $employees = DB::table('employees')
            ->select('id', 'employee_no', 'full_name', 'first_name', 'last_name', 'joining_date')
            ->where('employee_status', 'Active')
            ->whereNotNull('joining_date')
            ->get();

        $result = [];

        foreach ($employees as $emp) {
            $joined      = Carbon::parse($emp->joining_date);
            $anniversary = $joined->copy()->setYear($today->year);

            if ($anniversary->lt($today)) {
                $anniversary->addYear();
            }

            if ($anniversary->between($today, $thirtyDaysLater)) {
                $result[] = [
                    'id'               => $emp->id,
                    'employee_no'      => $emp->employee_no,
                    'full_name'        => $emp->full_name ?: trim($emp->first_name . ' ' . $emp->last_name),
                    'joining_date'     => $joined->toDateString(),
                    'years'            => $anniversary->year - $joined->year,
                    'anniversary_date' => $anniversary->toDateString(),
                ];
            }
        }

        usort($result, fn($a, $b) => $a['anniversary_date'] <=> $b['anniversary_date']);

        return $result;
    }
}
