<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gives every designation used by the standard T&A list at least HOLDERS_PER_DESIGNATION active
 * employees, so the T&A "responsible employee" quick-list has real people in it:
 *
 *   1. existing active employees who have NO designation yet are given it (preferring the designation's
 *      own department) — nobody who already has a designation is touched;
 *   2. if that is still not enough, new demo employees are created with it. Demo employees are marked by
 *      an identification no. starting "TADEMO-" and have no author.
 *
 * Everything done is recorded in storage/app/ta-demo-owners.json so remove() can undo it exactly:
 * given designations are cleared again (only where still unchanged) and demo employees are deleted.
 *
 * Direct database writes, so the employee change journal (employee_field_changes) is NOT updated.
 *
 * Safe to re-run: a designation that already has enough holders is skipped.
 * Undo:  (new \Database\Seeders\TaDemoOwnersSeeder)->remove()   (e.g. in tinker)
 * Run:   php artisan db:seed --class="Database\Seeders\TaDemoOwnersSeeder"
 *        (TaDemoDatasetSeeder runs it first on its own)
 *
 * DEV / TEST DATABASES ONLY — this edits real employee rows and invents people. Never run on production.
 */
class TaDemoOwnersSeeder extends Seeder
{
    public const HOLDERS_PER_DESIGNATION = 2;
    public const ID_PREFIX = 'TADEMO-';

    private const FIRST_NAMES = ['Nimal', 'Sunil', 'Kasun', 'Dilani', 'Nadeesha', 'Chamari', 'Ruwan', 'Thilini', 'Mahesh', 'Sanduni', 'Lahiru', 'Anusha', 'Pradeep', 'Madhavi', 'Isuru', 'Kavindi'];
    private const LAST_NAMES = ['Perera', 'Fernando', 'Silva', 'Jayawardena', 'Wickramasinghe', 'Gunasekara', 'Herath', 'Bandara', 'Rajapaksha', 'Senanayake', 'Dissanayake', 'Amarasinghe'];

    public function run(): void
    {
        $designations = DB::table('designations')
            ->join('ta_template_actions', 'ta_template_actions.designation_id', '=', 'designations.id')
            ->where('designations.is_active', true)
            ->distinct()
            ->orderBy('designations.id')
            ->get(['designations.id', 'designations.name', 'designations.department_id']);

        if ($designations->isEmpty()) {
            throw new RuntimeException('No standard T&A action has a designation yet — run TaDesignationsSeeder or map them on the T&A Master page first.');
        }

        $factoryId = $this->demoFactoryId();
        $hierarchies = DB::table('management_hierarchies')->orderBy('seq_no')->orderBy('id')->get(['id', 'name'])->all();
        if (empty($hierarchies)) {
            throw new RuntimeException('No management hierarchy exists, so demo employees cannot be created.');
        }

        $assigned = [];
        $created = [];
        DB::transaction(function () use ($designations, $factoryId, $hierarchies, &$assigned, &$created) {
            foreach ($designations as $designation) {
                $holders = DB::table('employees')->where('designation_id', $designation->id)->where('employee_status', 'Active')->count();
                $need = self::HOLDERS_PER_DESIGNATION - $holders;

                foreach ($this->employeesWithoutDesignation($designation->department_id, $need) as $employeeId) {
                    DB::table('employees')->where('id', $employeeId)->whereNull('designation_id')
                        ->update(['designation_id' => $designation->id, 'updated_at' => now()]);
                    $assigned[] = ['employee_id' => (int) $employeeId, 'designation_id' => (int) $designation->id];
                    $need--;
                }

                for (; $need > 0; $need--) {
                    $created[] = $this->createDemoEmployee($designation, $factoryId, $hierarchies);
                }
            }
        });

        $this->saveRecord($assigned);
        $this->report($designations->count(), $assigned, $created);
    }

    /** Undoes run(): clears the designations it gave and deletes the demo employees it created. */
    public function remove(): void
    {
        DB::transaction(function () {
            foreach ($this->readRecord() as $entry) {
                // Only if still as we left it — a designation changed by hand since stays.
                DB::table('employees')->where('id', $entry['employee_id'])->where('designation_id', $entry['designation_id'])
                    ->update(['designation_id' => null, 'updated_at' => now()]);
            }

            $demoIds = DB::table('employees')->where('identification_no', 'like', self::ID_PREFIX.'%')->pluck('id');
            if ($demoIds->isNotEmpty()) {
                DB::table('style_ta_actions')->whereIn('employee_id', $demoIds)->update(['employee_id' => null]);
                DB::table('employees')->whereIn('id', $demoIds)->delete();
            }
        });

        @unlink($this->recordPath());
    }

    /**
     * Pure: the demo person for sequence number $n (1-based). Deterministic and unique per $n, so a re-run
     * or an undo never has to guess; identification_no fits employees.identification_no (VARCHAR 20).
     *
     * @return array{first_name:string,last_name:string,gender:string,identification_no:string}
     */
    public static function demoPerson(int $n): array
    {
        $first = count(self::FIRST_NAMES);
        $last = count(self::LAST_NAMES);

        return [
            'first_name' => self::FIRST_NAMES[$n % $first],
            'last_name' => self::LAST_NAMES[($n + intdiv($n, $first)) % $last],
            'gender' => $n % 2 === 0 ? 'Female' : 'Male',
            'identification_no' => sprintf('%s%03d', self::ID_PREFIX, $n),
        ];
    }

    /**
     * Pure: a management hierarchy that suits the designation — a "manager" level for manager titles,
     * an executive/engineer level for the rest — falling back to the first hierarchy.
     *
     * @param array<int,object{id:int,name:string}> $hierarchies in seq_no order
     */
    public static function pickHierarchyId(array $hierarchies, string $designationName): int
    {
        $isManager = (bool) preg_match('/\bmanager\b|\bAM\b|\blead\b/i', $designationName);
        $wanted = $isManager ? ['manager', 'executive'] : ['executive', 'engineer', 'officer'];

        foreach ($wanted as $needle) {
            foreach ($hierarchies as $hierarchy) {
                if (stripos($hierarchy->name, $needle) !== false) {
                    return (int) $hierarchy->id;
                }
            }
        }

        return (int) $hierarchies[0]->id;
    }

    /** Active employees with no designation, those in $departmentId first, then oldest first. @return int[] */
    private function employeesWithoutDesignation(?int $departmentId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $query = DB::table('employees')
            ->whereNull('designation_id')
            ->where('employee_status', 'Active')
            ->where(function ($q) {
                $q->whereNull('identification_no')->orWhere('identification_no', 'not like', self::ID_PREFIX.'%');
            });
        if ($departmentId !== null) {
            $query->orderByRaw('CASE WHEN department_id = ? THEN 0 ELSE 1 END', [$departmentId]);
        }

        return $query->orderBy('id')->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** The factory most existing styles live in, so demo employees are visible where the styles are. */
    private function demoFactoryId(): int
    {
        $id = DB::table('factory_style')->select('factory_id')->groupBy('factory_id')
            ->orderByRaw('COUNT(*) DESC')->value('factory_id')
            ?? DB::table('factories')->orderBy('id')->value('id');

        if (!$id) {
            throw new RuntimeException('No factory exists, so demo employees cannot be created.');
        }

        return (int) $id;
    }

    /** @return array{id:int,name:string,designation:string} */
    private function createDemoEmployee(object $designation, int $factoryId, array $hierarchies): array
    {
        $next = (int) DB::table('employees')->where('identification_no', 'like', self::ID_PREFIX.'%')->count() + 1;
        $person = self::demoPerson($next);
        // Keep the identification no. unique even if a number was deleted out of sequence.
        while (DB::table('employees')->where('identification_no', $person['identification_no'])->exists()) {
            $person = self::demoPerson(++$next);
        }

        $now = now();
        $id = DB::table('employees')->insertGetId([
            'factory_id' => $factoryId,
            'identification_no' => $person['identification_no'],
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'full_name' => $person['first_name'].' '.$person['last_name'],
            'gender' => $person['gender'],
            'management_hierarchy_id' => self::pickHierarchyId($hierarchies, $designation->name),
            'department_id' => $designation->department_id,
            'designation_id' => $designation->id,
            'employment_type' => 'Permanent',
            'employee_category' => 'Indirect',
            'employee_status' => 'Active',
            'joining_date' => date('Y-m-d', strtotime('2022-01-03 +'.($next * 17).' days')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // The app generates the number from the row id (Employee::boot); do the same.
        DB::table('employees')->where('id', $id)->update(['employee_no' => 'EMP-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);

        return ['id' => (int) $id, 'name' => $person['first_name'].' '.$person['last_name'], 'designation' => $designation->name];
    }

    private function recordPath(): string
    {
        return storage_path('app/ta-demo-owners.json');
    }

    /** @return array<int,array{employee_id:int,designation_id:int}> */
    private function readRecord(): array
    {
        if (!is_file($this->recordPath())) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->recordPath()), true);

        return is_array($decoded['assigned'] ?? null) ? $decoded['assigned'] : [];
    }

    /** Appends to any earlier record so an undo after several runs still covers everything. */
    private function saveRecord(array $assigned): void
    {
        $all = array_merge($this->readRecord(), $assigned);
        if (empty($all)) {
            return;
        }
        if (!is_dir(dirname($this->recordPath()))) {
            mkdir(dirname($this->recordPath()), 0775, true);
        }
        file_put_contents($this->recordPath(), json_encode(['assigned' => $all], JSON_PRETTY_PRINT));
    }

    private function report(int $designationCount, array $assigned, array $created): void
    {
        if (!$this->command) {
            return;
        }
        $this->command->info(sprintf(
            'T&A owners: %d designations checked (%d holders each) — %d existing employees given a designation, %d demo employees created.',
            $designationCount,
            self::HOLDERS_PER_DESIGNATION,
            count($assigned),
            count($created)
        ));
        foreach ($created as $employee) {
            $this->command->line(sprintf('  new  EMP#%-5d %-22s %s', $employee['id'], $employee['name'], $employee['designation']));
        }
        if (!empty($assigned)) {
            $this->command->line('  Existing employees given a designation are listed in storage/app/ta-demo-owners.json (used by remove()).');
        }
    }
}
