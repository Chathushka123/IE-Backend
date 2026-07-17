<?php

namespace App\Http\Repositories;

use App\Department;
use App\Employee;
use App\LineCategory;
use App\LinePlan;
use App\Product;
use App\ProductionLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public static function getOverview(): array
    {
        return [
            'kpis'                       => self::getKpis(),
            'teams_by_department'        => self::getTeamsByDepartment(),
            'line_plan_status_breakdown' => self::getLinePlanStatusBreakdown(),
            'upcoming_line_plans'        => self::getUpcomingLinePlans(),
        ];
    }

    private static function getKpis(): array
    {
        return [
            'employees_total'   => Employee::count(),
            'employees_active'  => Employee::where('employee_status', 'Active')->count(),
            'departments_total' => Department::where('is_active', true)->count(),
            'sections_total'    => LineCategory::where('is_active', true)->count(),
            'teams_total'       => ProductionLine::count(),
            'teams_active'      => ProductionLine::where('is_active', true)->count(),
            'products_total'    => Product::count(),
            'products_active'   => Product::where('is_active', true)->count(),
            'line_plans_active' => self::linePlansInFactoryScope()
                ->where('status', 'in_progress')
                ->count(),
            'line_plans_upcoming' => self::linePlansInFactoryScope()
                ->where('status', 'planned')
                ->whereBetween('planned_start_date', [Carbon::today(), Carbon::today()->addDays(7)])
                ->count(),
        ];
    }

    /**
     * LinePlan has no factory_id of its own — it's scoped indirectly through
     * its production line, so whereHas() (which runs ProductionLine's own
     * ScopedToFactory global scope as a subquery) is used instead of a raw join.
     */
    private static function linePlansInFactoryScope()
    {
        return LinePlan::whereHas('productionLine');
    }

    private static function getTeamsByDepartment(): array
    {
        return ProductionLine::join('departments', 'production_lines.department_id', '=', 'departments.id')
            ->select('departments.description as label', DB::raw('COUNT(*) as count'))
            ->groupBy('departments.id', 'departments.description')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getLinePlanStatusBreakdown(): array
    {
        return self::linePlansInFactoryScope()
            ->select('status as label', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    private static function getUpcomingLinePlans(): array
    {
        return self::linePlansInFactoryScope()
            ->with([
                'productionLine:id,description,code',
                'product:id,description,style_code',
            ])
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereNotNull('planned_start_date')
            ->whereBetween('planned_start_date', [Carbon::today(), Carbon::today()->addDays(7)])
            ->orderBy('planned_start_date')
            ->limit(8)
            ->get(['id', 'production_line_id', 'product_id', 'planned_start_date', 'planned_end_date', 'status'])
            ->map(fn ($plan) => [
                'id'                 => $plan->id,
                'production_line'    => optional($plan->productionLine)->description,
                'product'            => optional($plan->product)->style_code ?? optional($plan->product)->description,
                'planned_start_date' => optional($plan->planned_start_date)->toDateString(),
                'planned_end_date'   => optional($plan->planned_end_date)->toDateString(),
                'status'             => $plan->status,
            ])
            ->toArray();
    }
}
