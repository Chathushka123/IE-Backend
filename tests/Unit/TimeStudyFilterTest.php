<?php

namespace Tests\Unit;

use App\Exports\TimeStudyExport;
use App\Http\Repositories\TimeStudyDashboardRepository;
use Tests\TestCase;

class TimeStudyFilterTest extends TestCase
{
    public function test_dashboard_query_filters_on_conducted_by_not_created_by(): void
    {
        $query = TimeStudyDashboardRepository::baseQuery(['conducted_by_ids' => [3, 7]]);

        $this->assertStringContainsString('`time_studies`.`conducted_by_id` in (?, ?)', $query->toSql());
        $this->assertStringNotContainsString('created_by_id', $query->toSql());
        $this->assertSame([3, 7], $query->getBindings());
    }

    public function test_dashboard_query_ignores_empty_conducted_by(): void
    {
        $query = TimeStudyDashboardRepository::baseQuery(['conducted_by_ids' => []]);

        $this->assertStringNotContainsString('conducted_by_id', $query->toSql());
    }

    public function test_export_query_filters_on_conducted_by(): void
    {
        $query = TimeStudyExport::filteredQuery(['conducted_by_ids' => [5]]);

        $this->assertStringContainsString('`conducted_by_id` in (?)', $query->toSql());
        $this->assertSame([5], $query->getBindings());
    }
}
