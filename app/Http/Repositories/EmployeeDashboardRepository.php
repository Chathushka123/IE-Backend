<?php

namespace App\Http\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\FactoryContext;
use App\Support\RequestTimezone;

class EmployeeDashboardRepository
{
    /**
     * DB::table('employees') pre-scoped to the current request's factory context.
     * This repository uses the raw query builder (not the Employee Eloquent model),
     * so it doesn't pick up Employee::boot()'s global factory scope automatically —
     * every query here must go through this instead of DB::table('employees') directly.
     */
    private static function employeesQuery()
    {
        $query = DB::table('employees');
        if (!FactoryContext::isBypassed() && FactoryContext::isResolved()) {
            $query->whereIn('employees.factory_id', FactoryContext::ids());
        }
        return $query;
    }

    public static function getDashboardData(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : RequestTimezone::todayDate()->subMonths(11)->startOfMonth();
        $to   = $toDate   ? Carbon::parse($toDate)->endOfDay()     : RequestTimezone::todayDate()->endOfMonth();

        return [
            'kpis'                  => self::getKpis(),
            'by_status'             => self::getByStatus(),
            'by_department'         => self::getByDimension('departments', 'department_id'),
            'by_management_hierarchy' => self::getByDimension('management_hierarchies', 'management_hierarchy_id'),
            'by_designation'        => self::getByDesignation(),
            'by_employment_type'    => self::getByEnumField('employment_type'),
            'by_gender'             => self::getByEnumField('gender'),
            'by_production_line'    => self::getByDimension('teams', 'team_id'),
            'headcount_trend'       => self::getJoiningTrend($from, $to),
            'attrition_trend'       => self::getAttritionTrend($from, $to),
            'upcoming_anniversaries' => self::getUpcomingAnniversaries(),
        ];
    }

    private static function getKpis(): array
    {
        $total = self::employeesQuery()->count();

        $active      = self::employeesQuery()->where('employee_status', 'Active')->count();
        $resigned    = self::employeesQuery()->where('employee_status', 'Resigned')->count();
        $terminated  = self::employeesQuery()->where('employee_status', 'Terminated')->count();

        $now          = RequestTimezone::todayDate();
        $currentMonth = $now->month;
        $currentYear  = $now->year;

        $newThisMonth = self::employeesQuery()
            ->whereMonth('joining_date', $currentMonth)
            ->whereYear('joining_date', $currentYear)
            ->count();

        $pendingConfirmation = self::employeesQuery()
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
        return self::employeesQuery()
            ->select('employee_status as label', DB::raw('COUNT(*) as count'))
            ->groupBy('employee_status')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getByDimension(string $table, string $fk): array
    {
        return self::employeesQuery()
            ->join($table, "employees.{$fk}", '=', "{$table}.id")
            ->select("{$table}.name as label", DB::raw('COUNT(*) as count'))
            ->whereNotNull("employees.{$fk}")
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getByDesignation(): array
    {
        return self::employeesQuery()
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('designations.name as label', DB::raw('COUNT(*) as count'))
            ->whereNotNull('employees.designation_id')
            ->groupBy('designations.id', 'designations.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private static function getByEnumField(string $field): array
    {
        return self::employeesQuery()
            ->select("{$field} as label", DB::raw('COUNT(*) as count'))
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getJoiningTrend(Carbon $from, Carbon $to): array
    {
        $rows = self::employeesQuery()
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
        $rows = self::employeesQuery()
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
        $today          = RequestTimezone::todayDate();
        $thirtyDaysLater = $today->copy()->addDays(30);

        $employees = self::employeesQuery()
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
