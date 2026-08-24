<?php

use App\Employee;
use App\Factory;
use App\ManagementHierarchy;
use App\Department;
use App\Team;
use App\Designation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a baseline EmployeeFieldChange row (old_value = null) for every
 * tracked field already set on employees that existed before the "employee
 * journey" feature shipped — those rows never got the baseline that
 * EmployeeRepository::createRec() now writes for every new employee.
 * Employees that already have journey rows (created after the feature
 * shipped) are skipped so they don't get a duplicate baseline.
 */
class BackfillEmployeeFieldChanges extends Migration
{
    private const TRACKED_FIELDS = [
        'marital_status',
        'factory_id',
        'management_hierarchy_id',
        'department_id',
        'team_id',
        'designation_id',
        'employment_type',
        'employee_category',
        'employee_status',
        'reporting_manager_id',
    ];

    private const FIELD_LOOKUP_MODELS = [
        'factory_id' => Factory::class,
        'management_hierarchy_id' => ManagementHierarchy::class,
        'department_id' => Department::class,
        'team_id' => Team::class,
        'designation_id' => Designation::class,
    ];

    /** Marks rows this migration inserted so down() can remove exactly those and nothing else. */
    private const BACKFILL_MARKER = 'System (initial backfill)';

    public function up()
    {
        $alreadyTracked = DB::table('employee_field_changes')->pluck('employee_id')->unique();

        Employee::withoutGlobalScopes()
            ->whereNotIn('id', $alreadyTracked)
            ->orderBy('id')
            ->chunkById(200, function ($employees) {
                $rows = [];

                foreach ($employees as $employee) {
                    foreach (self::TRACKED_FIELDS as $field) {
                        $value = $employee->$field;
                        if ($value === null) {
                            continue;
                        }
                        $rows[] = [
                            'employee_id' => $employee->id,
                            'field' => $field,
                            'old_value' => null,
                            'new_value' => (string) $value,
                            'old_label' => null,
                            'new_label' => $this->resolveFieldLabel($field, $value),
                            'changed_by_user_id' => null,
                            'changed_by_name' => self::BACKFILL_MARKER,
                            'created_at' => $employee->created_at ?? now(),
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('employee_field_changes')->insert($rows);
                }
            });
    }

    public function down()
    {
        DB::table('employee_field_changes')->where('changed_by_name', self::BACKFILL_MARKER)->delete();
    }

    private function resolveFieldLabel($field, $value)
    {
        if ($field === 'reporting_manager_id') {
            $manager = Employee::withoutGlobalScopes()->find($value);
            if (!$manager) {
                return null;
            }
            $name = $manager->full_name ?: trim($manager->first_name . ' ' . $manager->last_name);
            return "{$manager->employee_no} – {$name}";
        }

        $modelClass = self::FIELD_LOOKUP_MODELS[$field] ?? null;
        if ($modelClass === null) {
            // Plain enum/string field — the raw value is already human-readable.
            return $value;
        }

        $record = $modelClass::find($value);
        return $record ? $record->name : null;
    }
}
