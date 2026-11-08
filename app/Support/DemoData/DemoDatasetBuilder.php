<?php

namespace App\Support\DemoData;

use App\Exceptions\GeneralException;
use App\Http\Repositories\OperationSmvRepository;
use App\Http\Repositories\SkillMatrixCalculationRepository;
use App\Http\Repositories\TeamPlanRepository;
use App\Http\Repositories\TimeStudyCycleRepository as Cycles;
use App\Http\Repositories\TimeStudyPlanRepository as Plans;
use App\Http\Repositories\TimeStudyRepository;
use App\Http\Repositories\TimeStudyReviewRepository;
use App\Http\Repositories\TimeStudySmvSessionRepository;
use App\Http\Repositories\TimeStudyTemplateRepository as Templates;
use App\Support\FactoryContext;
use App\Support\TimeStudy\TemplateDefinition;
use App\TimeStudyPlanItem;
use App\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Builds a believable apparel-factory dataset on top of the HR and product master data that is
 * already in the database: skills, operations, styles, teams, templates, time studies (created
 * through the real repositories, so the server's own rules, SMV and quality scoring decide their
 * status), review decisions, SMV sessions and team plans.
 *
 * Deterministic for a given seed. Refuses to run twice. Never touches HR rows except to put the
 * sewing operators of the two chosen factories into teams.
 */
class DemoDatasetBuilder
{
    private const OPERATOR_DESIGNATION = 'Sewing Machine Operator';

    /** Operator tiers: share of operators, mean efficiency %, abilities (operations they have been timed on). */
    private const TIERS = [
        'top' => ['share' => 0.12, 'mean' => 92, 'abilities' => [4, 6]],
        'upper' => ['share' => 0.28, 'mean' => 78, 'abilities' => [3, 5]],
        'lower' => ['share' => 0.40, 'mean' => 63, 'abilities' => [2, 4]],
        'new' => ['share' => 0.20, 'mean' => 47, 'abilities' => [1, 2]],
    ];

    private Carbon $today;
    private object $admin;
    /** @var int[] */
    private array $reviewers = [];
    /** @var array<string,int> factory code => id */
    private array $factory = [];
    private array $productCategory = [];
    private array $machineType = [];
    private array $skill = [];
    private array $grade = [];
    private array $baseCategory = [];
    private array $reason = [];
    /** @var array<int,array> operation id => details */
    private array $operations = [];
    /** @var array<string,int[]> product category name => operation ids in routing order */
    private array $operationsByCategory = [];
    /** @var array<int,array> style id => details */
    private array $styles = [];
    /** @var array<int,array> team id => [id, factory, categories, operators[]] */
    private array $teams = [];
    private array $templateIds = [];
    private array $resolveCache = [];
    private array $studyIds = [];
    private array $stats = [];

    public function __construct(private Closure $say, private int $targetStudies = 1000, private int $seed = 20261025)
    {
    }

    public function run(): array
    {
        mt_srand($this->seed);
        FactoryContext::reset();
        $this->today = Carbon::today();

        $this->loadReferences();
        $this->refuseSecondRun();

        $this->say('Masters');
        $this->buildMasters();
        $this->say('Styles');
        $this->buildStyles();
        $this->say('Teams and operators');
        $this->buildTeams();
        $this->say('Templates');
        $this->buildTemplates();
        $this->say('Time studies');
        $this->buildStudies();
        $this->say('Reviews');
        $this->buildReviews();
        $this->say('SMV sessions');
        $this->buildSmvSessions();
        $this->say('Team plans');
        $this->buildTeamPlans();
        $this->say('Study plans');
        $this->buildStudyPlans();
        $this->say('Combined cycles');
        $this->buildCycles();
        $this->say('Skill matrix runs');
        $this->buildMatrixRuns();

        return $this->stats;
    }

    private function say(string $message): void
    {
        ($this->say)($message);
    }

    // ---------------------------------------------------------------------
    // References
    // ---------------------------------------------------------------------

    private function loadReferences(): void
    {
        $admin = DB::table('users')->where('email', 'sysadmin@gmail.com')->first() ?? DB::table('users')->orderBy('id')->first();
        if (!$admin) {
            throw new GeneralException('No users found; restore the UAT backup first.');
        }
        $this->admin = $admin;
        $this->reviewers = DB::table('users')->where('id', '!=', $admin->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all() ?: [(int) $admin->id];
        Auth::guard()->setUser(User::findOrFail($admin->id));

        foreach (['AXN2' => 'Axon Sri Lanka', 'AXN3' => 'Axon Egypt'] as $code => $name) {
            $row = DB::table('factories')->where('code', $code)->first();
            if (!$row || $row->name !== $name) {
                throw new GeneralException("Factory {$code} should be {$name}; found " . ($row->name ?? 'nothing') . '.');
            }
            $this->factory[$code] = (int) $row->id;
        }

        $this->productCategory = DB::table('product_categories')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        foreach (array_keys(DemoCatalog::RECIPES) as $name) {
            if (!isset($this->productCategory[$name])) {
                throw new GeneralException("Product category '{$name}' is missing.");
            }
        }
        $this->machineType = DB::table('machine_types')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        foreach (DemoCatalog::BASE_OPERATIONS as $op => [, $machine]) {
            if (!isset($this->machineType[$machine])) {
                throw new GeneralException("Machine type '{$machine}' (needed by '{$op}') is missing.");
            }
        }
    }

    private function refuseSecondRun(): void
    {
        if (DB::table('base_skills')->where('code', 'like', DemoCatalog::PREFIX . '%')->exists()) {
            throw new GeneralException('The demo dataset is already in this database. Restore a fresh copy to start over.');
        }
    }

    // ---------------------------------------------------------------------
    // Masters
    // ---------------------------------------------------------------------

    private function stamp(array $row): array
    {
        $now = now();

        return $row + ['created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id, 'created_at' => $now, 'updated_at' => $now];
    }

    private function insert(string $table, array $row): int
    {
        return (int) DB::table($table)->insertGetId($this->stamp($row));
    }

    private function buildMasters(): void
    {
        foreach (DemoCatalog::SKILLS as $code => $name) {
            $this->skill[$code] = $this->insert('base_skills', ['code' => DemoCatalog::PREFIX . $code, 'name' => $name, 'is_active' => 1]);
        }
        foreach (DemoCatalog::GRADES as $code => [$name, $level]) {
            $this->grade[$code] = $this->insert('operation_grades', ['code' => DemoCatalog::PREFIX . 'GR-' . $code, 'name' => $name, 'level' => $level, 'is_active' => 1]);
        }
        foreach (DemoCatalog::CATEGORIES as $i => $name) {
            $this->baseCategory[$name] = $this->insert('base_operation_categories', ['code' => DemoCatalog::PREFIX . 'BC-' . ($i + 1), 'name' => $name, 'is_active' => 1]);
        }
        foreach (DemoCatalog::DOWNTIME_REASONS as $code => $name) {
            $this->reason[$code] = (int) (DB::table('time_study_downtime_reasons')->where('code', $code)->value('id')
                ?? $this->insert('time_study_downtime_reasons', ['code' => $code, 'name' => $name, 'is_active' => 1]));
        }
        // Added by a migration, so it is already there; the cycle demo records time spent moving between operations.
        $this->reason['OPERATION_CHANGE'] = (int) (DB::table('time_study_downtime_reasons')->where('code', 'OPERATION_CHANGE')->value('id')
            ?? $this->insert('time_study_downtime_reasons', ['code' => 'OPERATION_CHANGE', 'name' => 'Operation Change', 'is_active' => 1]));

        $baseOperation = [];
        $n = 0;
        foreach (DemoCatalog::BASE_OPERATIONS as $name => [$category]) {
            $baseOperation[$name] = $this->insert('base_operations', [
                'code' => DemoCatalog::PREFIX . 'BO-' . str_pad((string) ++$n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'base_operation_category_id' => $this->baseCategory[$category],
                'is_active' => 1,
            ]);
        }

        $count = 0;
        foreach (DemoCatalog::RECIPES as $category => $recipe) {
            $sequence = 0;
            foreach ($recipe as $baseName => $multiplier) {
                [, $machine, $baseSmv, $gradeCode, $skills] = DemoCatalog::BASE_OPERATIONS[$baseName];
                $smv = round($baseSmv * $multiplier * $this->rand(0.94, 1.06), 4);
                [$top, $upper, $lower] = DemoCatalog::BANDS[$gradeCode];
                $id = $this->insert('operations', [
                    'base_operation_id' => $baseOperation[$baseName],
                    'product_category_id' => $this->productCategory[$category],
                    'machine_type_id' => $this->machineType[$machine],
                    'description' => "{$category} - {$baseName}",
                    'code' => DemoCatalog::PREFIX . 'OP-' . str_pad((string) ++$count, 3, '0', STR_PAD_LEFT),
                    'grade_id' => $this->grade[$gradeCode],
                    'sequence_no' => ++$sequence,
                    'smv' => $smv,
                    'expected_top_level_efficiency' => $top,
                    'expected_upper_mid_level_efficiency' => $upper,
                    'expected_lower_mid_level_efficiency' => $lower,
                    'calculation_method' => in_array($gradeCode, ['TRN', 'JNR'], true) && $this->chance(0.3) ? 'median' : 'mean',
                    'mode_bin_size_pct' => 1,
                    'is_active' => 1,
                ]);
                foreach ($skills as $skillCode) {
                    $this->insert('operation_skill', ['operation_id' => $id, 'base_skill_id' => $this->skill[$skillCode], 'is_active' => 1]);
                }
                $this->operations[$id] = ['id' => $id, 'category' => $category, 'base' => $baseName, 'smv' => $smv, 'grade' => $gradeCode, 'skills' => array_map(fn ($c) => $this->skill[$c], $skills)];
                $this->operationsByCategory[$category][] = $id;
            }
        }
        $this->stats['operations'] = $count;
    }

    private function buildStyles(): void
    {
        $customers = DB::table('customers')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        foreach (DemoCatalog::EXTRA_SEASONS as $customerCode => $names) {
            foreach ($names as $name) {
                if (!DB::table('seasons')->where('customer_id', $customers[$customerCode])->where('name', $name)->exists()) {
                    $this->insert('seasons', ['code' => DemoCatalog::PREFIX . $customerCode . '-' . $name, 'name' => $name, 'customer_id' => $customers[$customerCode]]);
                }
            }
        }

        foreach (DemoCatalog::STYLES as $i => [$name, $customerCode, $seasonName, $category, $factoryCodes]) {
            $seasonId = DB::table('seasons')->where('customer_id', $customers[$customerCode])->where('name', $seasonName)->value('id')
                ?? DB::table('seasons')->where('customer_id', $customers[$customerCode])->value('id');
            $id = $this->insert('styles', [
                'name' => $name,
                'style_code' => DemoCatalog::PREFIX . 'ST-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'style_description' => "{$category} for {$customerCode}",
                'product_category_id' => $this->productCategory[$category],
                'customer_id' => $customers[$customerCode],
                'season_id' => $seasonId,
                'colors' => json_encode($this->pick([['Navy', 'White'], ['Black', 'Heather Grey', 'Olive'], ['Sky', 'Coral', 'White']])),
                'sizes' => json_encode(['XS', 'S', 'M', 'L', 'XL']),
                'customer_requested_delivery_date' => $this->today->copy()->addDays(mt_rand(45, 150))->toDateString(),
                'planned_efficiency_pct' => mt_rand(55, 75),
                'is_active' => 1,
            ]);
            foreach ($factoryCodes as $code) {
                DB::table('factory_style')->insert(['factory_id' => $this->factory[$code], 'style_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($this->operationsByCategory[$category] as $sequence => $operationId) {
                $this->insert('style_operations', [
                    'style_id' => $id,
                    'operation_id' => $operationId,
                    'sequence_no' => $sequence + 1,
                    // Four in ten bulletin lines carry the style's own SMV; the rest follow the operation.
                    'smv' => $this->chance(0.4) ? round($this->operations[$operationId]['smv'] * $this->rand(0.9, 1.15), 4) : null,
                    'is_active' => 1,
                ]);
            }
            $this->styles[$id] = ['id' => $id, 'category' => $category, 'factories' => array_map(fn ($c) => $this->factory[$c], $factoryCodes)];
        }
        $this->stats['styles'] = count($this->styles);
    }

    // ---------------------------------------------------------------------
    // Teams and operators
    // ---------------------------------------------------------------------

    private function buildTeams(): void
    {
        $designationId = DB::table('designations')->where('name', self::OPERATOR_DESIGNATION)->value('id');
        $sewingDepartment = DB::table('departments')->where('name', 'Sewing')->value('id');
        $section = DB::table('teams')->where('factory_id', $this->factory['AXN2'])->where('code', 'T01')->value('section_id');
        if (!$designationId || !$sewingDepartment || !$section) {
            throw new GeneralException('Expected the Sewing department, its sections/teams and the Sewing Machine Operator designation.');
        }

        $teamIdsByFactory = [$this->factory['AXN2'] => [], $this->factory['AXN3'] => []];
        foreach (DemoCatalog::TEAM_FOCUS as $code => $focus) {
            $factoryId = str_starts_with($code, 'DM-E') ? $this->factory['AXN3'] : $this->factory['AXN2'];
            $existing = DB::table('teams')->where('factory_id', $factoryId)->where('code', $code)->first();
            if ($existing) {
                $id = (int) $existing->id;
                DB::table('teams')->where('id', $id)->update(['target_efficiency_pct' => 70, 'updated_at' => now()]);
            } else {
                $id = $this->insert('teams', [
                    'factory_id' => $factoryId, 'name' => 'Axon Egypt Line ' . substr($code, -1), 'code' => $code, 'section_id' => $section,
                    'department_id' => $sewingDepartment, 'is_active' => 1, 'working_minutes_per_day' => 480, 'target_efficiency_pct' => 70,
                ]);
            }
            $teamIdsByFactory[$factoryId][] = $id;
            $this->teams[$id] = ['id' => $id, 'code' => $code, 'factory' => $factoryId, 'focus' => $focus, 'operators' => []];
        }

        foreach ($teamIdsByFactory as $factoryId => $teamIds) {
            $operators = DB::table('employees')->where('factory_id', $factoryId)->where('designation_id', $designationId)
                ->where('employee_status', 'Active')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $perTeam = (int) ceil(count($operators) / max(1, count($teamIds)));
            foreach (array_chunk($operators, $perTeam) as $i => $chunk) {
                $teamId = $teamIds[$i];
                DB::table('employees')->whereIn('id', $chunk)->update(['team_id' => $teamId, 'updated_at' => now()]);
                $this->teams[$teamId]['operators'] = $chunk;
            }
        }
        $this->stats['operators_in_teams'] = array_sum(array_map(fn ($t) => count($t['operators']), $this->teams));
    }

    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------

    private function buildTemplates(): void
    {
        $machineCategory = DB::table('machine_categories')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        $sewing = ['field' => 'machine_category', 'ref_id' => $machineCategory['Sewing']];
        $floor = ['field' => 'study_type', 'ref_id' => 2];

        $standard = TemplateDefinition::defaults('perf', 'standard');
        $standard['fields'] = [
            ['label' => 'Fabric', 'type' => 'choice', 'required' => true, 'options' => DemoCatalog::FABRICS],
            ['label' => 'Machine number', 'type' => 'text', 'required' => true, 'options' => []],
            ['label' => 'Bundle size', 'type' => 'number', 'required' => false, 'options' => []],
        ];

        // Each operation is timed on at least two operators, and each operator on one operation.
        $standard['layout'] = ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'one'];

        $line = TemplateDefinition::defaults('perf', 'standard');
        $line['rules']['min_laps'] = ['value' => 12, 'severity' => 'block'];
        $line['rules']['outlier'] = ['pct' => 25, 'severity' => 'warn'];
        $line['rules']['variation'] = ['pct' => 18, 'severity' => 'warn'];
        $line['rules']['down_time'] = ['pct' => 12, 'severity' => 'warn'];
        $line['rules']['first_last'] = 'first';
        $line['approval'] = 'always';
        // One operator per operation; an operator may take several operations, one after another.
        $line['layout'] = ['operators_per_operation' => ['mode' => 'one', 'min' => 2], 'operations_per_operator' => 'several'];
        $line['fields'] = [['label' => 'Shift', 'type' => 'choice', 'required' => true, 'options' => ['Day', 'Night']]];

        $finishing = TemplateDefinition::defaults('perf', 'standard');
        $finishing['rules']['min_laps'] = ['value' => 6, 'severity' => 'warn'];
        $finishing['approval'] = 'never';

        $bra = TemplateDefinition::defaults('perf', 'strict');
        $bra['rules']['min_laps'] = ['value' => 10, 'severity' => 'block'];
        $bra['rules']['outlier'] = ['pct' => 25, 'severity' => 'warn'];
        $bra['rules']['variation'] = ['pct' => 18, 'severity' => 'warn'];
        $bra['approval'] = 'warnings';
        $bra['fields'] = [['label' => 'Cup size', 'type' => 'choice', 'required' => true, 'options' => ['A', 'B', 'C', 'D']]];

        $trial = TemplateDefinition::defaults('perf', 'explore');
        $trial['manual_only'] = true;

        $smv = TemplateDefinition::defaults('smv', 'standard');
        $smv['rules']['min_operators'] = ['value' => 3, 'severity' => 'block'];
        $smv['rules']['min_laps'] = ['value' => 8, 'severity' => 'block'];
        $smv['rules']['rating_required'] = true;
        $smv['allowance_pct'] = 14;
        $smv['approval'] = 'always';

        // One operator on several operations in a cycle: timed in one pass, saved as one study per operation.
        $station = TemplateDefinition::defaults('perf', 'standard');
        $station['manual_only'] = true;
        $station['layout'] = ['operators_per_operation' => ['mode' => 'any', 'min' => 2], 'operations_per_operator' => 'combined'];

        $collarOperations = array_keys(array_filter($this->operations, fn ($op) => $op['base'] === 'Collar Attach'));

        $specs = [
            'sewing' => ['Sewing - General', 'perf', [$sewing], $standard, true],
            'floor' => ['Sewing - Production Floor', 'perf', [$sewing, $floor], $line, true],
            'finishing' => ['Finishing - Folding', 'perf', [['field' => 'machine_category', 'ref_id' => $machineCategory['Finishing']]], $finishing, true],
            'fusing' => ['Cutting & Fusing', 'perf', [['field' => 'machine_category', 'ref_id' => $machineCategory['Cutting']]], $finishing, true],
            'bra' => ['Bra - Precision Operations', 'perf', [['field' => 'product_category', 'ref_id' => $this->productCategory['Bra']], $sewing], $bra, true],
            'trial' => ['Trial - Pick Manually', 'perf', [], $trial, true],
            'old' => ['Basic Sewing (retired)', 'perf', [$sewing], $standard, true],
            'smv' => ['Establish SMV - Sewing', 'smv', [$sewing], $smv, true],
            'station' => ['Operator Stations - Combined Cycle', 'perf', [], $station, true],
            'collar_draft' => ['Collar & Cuff Precision (draft)', 'perf', array_map(fn ($id) => ['field' => 'operation', 'ref_id' => $id], $collarOperations), $bra, false],
        ];

        foreach ($specs as $key => [$name, $purpose, $conditions, $definition, $publish]) {
            $created = Templates::create(['name' => $name, 'purpose' => $purpose, 'conditions' => $conditions, 'definition' => $definition, 'manual_only' => $definition['manual_only'] ?? false]);
            $this->templateIds[$key] = (int) $created['id'];
            if ($publish) {
                Templates::publish($this->templateIds[$key]);
            }
        }
        Templates::archive($this->templateIds['old']);

        // A published template with an edit waiting, so the list shows "draft pending".
        Templates::saveDraft($this->templateIds['finishing'], ['definition' => array_replace_recursive($finishing, ['rules' => ['min_laps' => ['value' => 8, 'severity' => 'warn']]])]);
        $this->stats['templates'] = count($specs);
    }

    // ---------------------------------------------------------------------
    // Time studies
    // ---------------------------------------------------------------------

    private function buildStudies(): void
    {
        $pairs = $this->buildPairs();
        $counts = $this->studiesPerPair($pairs);

        $created = 0;
        $skipped = 0;
        $byStatus = [];
        foreach ($pairs as $i => $pair) {
            $dates = [];
            for ($k = 0; $k < $counts[$i]; $k++) {
                $dates[] = $this->today->copy()->subDays(mt_rand(1, 100));
            }
            usort($dates, fn ($a, $b) => $a <=> $b);

            foreach ($dates as $k => $date) {
                // Operators get steadily faster with practice.
                $efficiency = $pair['base'] + $k * $this->rand(0.6, 1.8) + $this->normal(0, 4.5);
                $study = $this->createStudy($pair, $date, max(22, min(118, $efficiency)));
                if ($study === null) {
                    $skipped++;
                    continue;
                }
                $created++;
                $byStatus[$study['status']] = ($byStatus[$study['status']] ?? 0) + 1;
                $this->studyIds[] = $study['id'];
            }
        }
        $this->stats['studies'] = $created;
        $this->stats['studies_skipped'] = $skipped;
        $this->stats['studies_by_status'] = $byStatus;
        $this->stats['operators_studied'] = count(array_unique(array_column($pairs, 'employee')));
    }

    /**
     * Who has been timed on what: per team, each sampled operator gets a tier and a few operations from the
     * line's product categories. A couple of operations per team are left with no operator or just one, so
     * Gap Analysis and Skill Depth have something to find.
     *
     * @return array<int,array{employee:int,operation:int,team:int,factory:int,base:float}>
     */
    private function buildPairs(): array
    {
        $pairs = [];
        foreach ($this->teams as $team) {
            $operations = [];
            foreach ($team['focus'] as $category) {
                $operations = array_merge($operations, $this->operationsByCategory[$category]);
            }
            $operations = array_values(array_unique($operations));
            $uncovered = array_slice($this->shuffle($operations), 0, 2);
            $single = array_slice(array_values(array_diff($this->shuffle($operations), $uncovered)), 0, 3);

            $members = $this->shuffle($team['operators']);
            // About three quarters of each line has been timed; the rest are on the roster only.
            $studied = array_slice($members, 0, (int) round(count($members) * 0.75));
            $holders = array_fill_keys($single, null);

            foreach ($studied as $index => $employeeId) {
                $tier = $this->tierFor($index, count($studied));
                [$low, $high] = self::TIERS[$tier]['abilities'];
                $base = self::TIERS[$tier]['mean'] + $this->normal(0, 5);
                // Stronger operators are trusted with the harder operations (with some chance in it).
                $scored = [];
                foreach (array_diff($operations, $uncovered) as $operationId) {
                    $scored[$operationId] = $this->difficulty($operationId) + $this->rand(-1.2, 1.2);
                }
                in_array($tier, ['top', 'upper'], true) ? arsort($scored) : asort($scored);
                $pool = array_keys($scored);
                $wanted = mt_rand($low, $high);
                $chosen = array_slice($pool, 0, $wanted + 4);
                $chosen = array_slice($this->shuffle($chosen), 0, $wanted);

                foreach ($chosen as $operationId) {
                    if (array_key_exists($operationId, $holders)) {
                        // An operation kept to a single operator: only the first one to claim it keeps it.
                        if ($holders[$operationId] !== null && $holders[$operationId] !== $employeeId) {
                            continue;
                        }
                        $holders[$operationId] = $employeeId;
                    }
                    $pairs[] = [
                        'employee' => $employeeId, 'operation' => $operationId, 'team' => $team['id'], 'factory' => $team['factory'],
                        'base' => $base - 3 * ($this->difficulty($operationId) - 3), 'tier' => $tier,
                    ];
                }
            }
        }

        return $pairs;
    }

    private function tierFor(int $index, int $total): string
    {
        $position = $total === 0 ? 0 : $index / $total;
        $edge = 0;
        foreach (self::TIERS as $tier => $config) {
            $edge += $config['share'];
            if ($position < $edge) {
                return $tier;
            }
        }

        return 'new';
    }

    private function difficulty(int $operationId): int
    {
        return DemoCatalog::GRADES[$this->operations[$operationId]['grade']][1];
    }

    /**
     * Spreads the study budget over the pairs: every pair at least once, Sri Lanka about four fifths of the total.
     *
     * @return int[] indexed like $pairs
     */
    private function studiesPerPair(array $pairs): array
    {
        $counts = array_fill(0, count($pairs), 1);
        foreach ([$this->factory['AXN2'] => 0.8, $this->factory['AXN3'] => 0.2] as $factoryId => $share) {
            $indexes = array_keys(array_filter($pairs, fn ($p) => $p['factory'] === $factoryId));
            $budget = (int) round($this->targetStudies * $share);
            if (count($indexes) > $budget) {
                // More pairs than studies: leave some pairs untimed rather than go over.
                foreach (array_slice($this->shuffle($indexes), 0, count($indexes) - $budget) as $drop) {
                    $counts[$drop] = 0;
                }
                continue;
            }
            for ($extra = $budget - count($indexes); $extra > 0 && $indexes; $extra--) {
                $pick = $indexes[mt_rand(0, count($indexes) - 1)];
                if ($counts[$pick] < 8) {
                    $counts[$pick]++;
                } else {
                    $extra++;
                    $indexes = array_values(array_diff($indexes, [$pick]));
                }
            }
        }

        return $counts;
    }

    /** @return array{id:int,status:string}|null */
    private function createStudy(array $pair, Carbon $date, float $efficiency): ?array
    {
        $operation = $this->operations[$pair['operation']];
        $style = $this->styleFor($pair);
        $type = $style !== null && $this->chance(0.6) ? 'production_floor' : 'interview_training';
        $styleId = $type === 'production_floor' ? $style : ($this->chance(0.3) ? $style : null);

        $resolved = $this->resolve($pair['operation'], $type);
        $active = $resolved['chosen'] ?? $resolved['fallback'];
        $overridden = false;
        if ($this->chance(0.03) && isset($resolved['alternatives'])) {
            $manual = collect($resolved['alternatives'])->firstWhere('manual_only', true);
            if ($manual) {
                $active = $manual;
                $overridden = true;
            }
        }

        $smv = $this->effectiveSmv($pair['operation'], $styleId);
        $conductor = $this->reviewers[mt_rand(0, count($this->reviewers) - 1)];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $payload = $this->studyPayload($pair, $operation, $date, $type, $styleId, $active, $overridden, $efficiency, $smv, $conductor, $attempt);
            try {
                $study = TimeStudyRepository::createRec($payload);
                $stamp = $date->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59));
                DB::table('time_studies')->where('id', $study->id)->update(['created_at' => $stamp, 'updated_at' => $stamp]);

                return ['id' => (int) $study->id, 'status' => $study->status];
            } catch (GeneralException $e) {
                // A blocking rule of the template: retry once or twice with a steadier, longer study.
                continue;
            }
        }

        return null;
    }

    private function resolve(int $operationId, string $type): array
    {
        $key = "{$operationId}:{$type}";

        return $this->resolveCache[$key] ??= Templates::resolve($operationId, 'perf', $type);
    }

    private function styleFor(array $pair): ?int
    {
        $options = array_filter($this->styles, fn ($s) => in_array($pair['factory'], $s['factories'], true) && $s['category'] === $this->operations[$pair['operation']]['category']);

        return $options ? array_values($options)[mt_rand(0, count($options) - 1)]['id'] : null;
    }

    private function effectiveSmv(int $operationId, ?int $styleId): float
    {
        $override = $styleId ? DB::table('style_operations')->where('style_id', $styleId)->where('operation_id', $operationId)->value('smv') : null;

        return (float) ($override ?? $this->operations[$operationId]['smv']);
    }

    private function studyPayload(array $pair, array $operation, Carbon $date, string $type, ?int $styleId, array $template, bool $overridden, float $efficiency, float $smv, int $conductor, int $attempt): array
    {
        $rules = $template['definition']['rules'];
        $minLaps = (int) $rules['min_laps']['value'];
        $excluded = ['both' => 2, 'first' => 1, 'last' => 1][$rules['first_last']] ?? 0;
        $noisy = $attempt === 0 && $this->chance(0.12);
        $short = $attempt === 0 && $this->chance(0.07);
        $count = $short ? max(3, $minLaps - 2) : $minLaps + $excluded + mt_rand(0, 4) + $attempt * 4;
        $cv = $noisy ? $this->rand(0.2, 0.32) : $this->rand(0.04, 0.085);
        $heavyDowntime = $attempt === 0 && $this->chance(0.1);
        $outlierLap = $attempt === 0 && $this->chance(0.08) ? mt_rand(2, max(2, $count - 1)) : null;

        $cycleMs = $smv * 60000 / ($efficiency / 100);
        $laps = [];
        $downtimes = [];
        $totalDown = 0;
        $totalDuration = 0;
        for ($n = 1; $n <= $count; $n++) {
            $net = $cycleMs * max(0.45, 1 + $this->normal(0, $cv));
            if ($outlierLap === $n) {
                $net *= $this->chance(0.7) ? $this->rand(1.7, 2.1) : 0.55;
            }
            $down = 0;
            if ($this->chance($heavyDowntime ? 0.7 : 0.22)) {
                $down = (int) round($net * ($heavyDowntime ? $this->rand(0.2, 0.45) : $this->rand(0.03, 0.12)));
            }
            $net = (int) round($net);
            $laps[] = ['lap_no' => $n, 'duration_ms' => $net + $down, 'down_time_ms' => $down, 'net_ms' => $net, 'is_excluded' => false, 'outlier_kept' => false];
            if ($down > 0) {
                $downtimes[] = ['lap_no' => $n, 'time_study_downtime_reason_id' => $this->reasonId(), 'duration_ms' => $down, 'note' => null];
            }
            $totalDown += $down;
            $totalDuration += $net + $down;
        }
        $hold = $this->chance(0.25) ? mt_rand(4000, 20000) : 0;

        return [
            'factory_id' => $pair['factory'],
            'study_date' => $date->toDateString(),
            'time_study_type' => $type,
            'operation_id' => $operation['id'],
            'style_id' => $styleId,
            'employee_id' => $pair['employee'],
            'conducted_by_id' => $conductor,
            'template_version_id' => $template['version_id'],
            'template_overridden' => $overridden,
            'field_values' => $this->fieldValues($template['definition']['fields']),
            'total_down_time_ms' => $totalDown,
            'total_hold_ms' => $hold,
            'total_cycle_ms' => $totalDuration + $hold,
            'skill_ids' => $operation['skills'],
            'laps' => $laps,
            'downtimes' => $downtimes,
        ];
    }

    private function fieldValues(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (!$field['required'] && $this->chance(0.25)) {
                continue;
            }
            $values[$field['key']] = match ($field['type']) {
                'choice' => $this->pick($field['options']),
                'number' => mt_rand(10, 60),
                'yesno' => $this->chance(0.5),
                default => 'M-' . mt_rand(100, 480),
            };
        }

        return $values;
    }

    private function reasonId(): int
    {
        $codes = array_keys(DemoCatalog::DOWNTIME_REASONS);

        return $this->reason[$this->weighted($codes, DemoCatalog::DOWNTIME_WEIGHTS)];
    }

    // ---------------------------------------------------------------------
    // Reviews
    // ---------------------------------------------------------------------

    private function buildReviews(): void
    {
        $queue = DB::table('time_studies')->whereIn('id', $this->studyIds)->where('status', 'needs_review')->where('purpose', 'perf')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $reasons = ['Laps vary too much for one operator. Please redo with a steadier run.', 'Down time is too high to trust this cycle time.', 'Wrong operation timed; re-record on the correct one.', 'An unusual lap was kept without a reason.'];
        $approved = $rejected = 0;
        foreach ($queue as $id) {
            $roll = mt_rand(1, 100);
            if ($roll > 70) {
                continue; // left waiting, so the queue is not empty
            }
            $conductor = (int) DB::table('time_studies')->where('id', $id)->value('conducted_by_id');
            $reviewer = $this->reviewerOtherThan($conductor);
            Auth::guard()->setUser(User::findOrFail($reviewer));
            try {
                if ($roll <= 55) {
                    TimeStudyReviewRepository::decide($id, 'approved', $this->chance(0.3) ? 'Checked against the video. Fine.' : null);
                    $approved++;
                } else {
                    TimeStudyReviewRepository::decide($id, 'rejected', $this->pick($reasons));
                    $rejected++;
                }
                $stamp = Carbon::parse(DB::table('time_studies')->where('id', $id)->value('study_date'))->addDays(mt_rand(0, 2))->setTime(mt_rand(9, 17), mt_rand(0, 59));
                DB::table('time_studies')->where('id', $id)->update(['reviewed_at' => $stamp]);
            } catch (GeneralException $e) {
                continue;
            }
        }
        Auth::guard()->setUser(User::findOrFail($this->admin->id));
        $this->stats['reviews'] = ['approved' => $approved, 'rejected' => $rejected, 'waiting' => count($queue) - $approved - $rejected];
    }

    private function reviewerOtherThan(int $userId): int
    {
        $others = array_values(array_diff(array_merge($this->reviewers, [(int) $this->admin->id]), [$userId]));

        return $others[mt_rand(0, count($others) - 1)];
    }

    // ---------------------------------------------------------------------
    // SMV sessions
    // ---------------------------------------------------------------------

    private function buildSmvSessions(): void
    {
        $slTeams = array_values(array_filter($this->teams, fn ($t) => $t['factory'] === $this->factory['AXN2']));
        $operationIds = [];
        foreach (['Tee shirt', 'Polo tee', 'Active bottom', 'Bra', 'Shirt', 'Short', 'PJ-top'] as $category) {
            $operationIds[] = $this->operationsByCategory[$category][array_search($category, array_keys(DemoCatalog::RECIPES), true) % 4 + 1];
        }

        $outcomes = ['approve_apply', 'approve_apply', 'approve_style', 'reject', 'wait', 'wait', 'wait'];
        $sessions = [];
        foreach ($operationIds as $i => $operationId) {
            $team = $slTeams[$i % count($slTeams)];
            $operators = array_slice($this->shuffle($team['operators']), 0, mt_rand(3, 5));
            $category = $this->operations[$operationId]['category'];
            $style = collect($this->styles)->first(fn ($s) => $s['category'] === $category && in_array($this->factory['AXN2'], $s['factories'], true));
            $styleId = $outcomes[$i] === 'approve_style' && $style ? $style['id'] : null;
            $template = Templates::resolve($operationId, 'smv', 'interview_training')['chosen'] ?? null;
            if ($template === null) {
                continue;
            }

            $smv = $this->effectiveSmv($operationId, $styleId);
            // The standard people actually run at differs from the stored SMV by a few percent either way.
            $truth = $smv * $this->rand(0.88, 1.12);
            $runs = [];
            foreach ($operators as $employeeId) {
                $rating = mt_rand(88, 112);
                $laps = [];
                $total = 0;
                for ($n = 1; $n <= mt_rand(10, 12); $n++) {
                    $net = (int) round($truth * 60000 / ($rating / 100) * max(0.6, 1 + $this->normal(0, 0.05)));
                    $laps[] = ['lap_no' => $n, 'duration_ms' => $net, 'down_time_ms' => 0, 'is_excluded' => false, 'outlier_kept' => false];
                    $total += $net;
                }
                $runs[] = [
                    'employee_id' => $employeeId, 'rating_pct' => $rating, 'total_down_time_ms' => 0, 'total_hold_ms' => 0, 'total_cycle_ms' => $total,
                    'skill_ids' => $this->operations[$operationId]['skills'], 'laps' => $laps, 'downtimes' => [],
                ];
            }

            try {
                $session = TimeStudySmvSessionRepository::create([
                    'factory_id' => $this->factory['AXN2'], 'study_date' => $this->today->copy()->subDays(mt_rand(3, 40))->toDateString(),
                    'time_study_type' => $styleId ? 'production_floor' : 'interview_training', 'operation_id' => $operationId, 'style_id' => $styleId,
                    'conducted_by_id' => $this->reviewers[$i % count($this->reviewers)], 'template_version_id' => $template['version_id'],
                    'field_values' => [], 'runs' => $runs,
                ]);
            } catch (GeneralException $e) {
                $this->say('  skipped an SMV session: ' . $e->getMessage());
                continue;
            }
            $sessions[] = [$session['study_ids'][0], $outcomes[$i]];
        }

        $applied = 0;
        foreach ($sessions as [$leadId, $outcome]) {
            try {
                Auth::guard()->setUser(User::findOrFail($this->reviewerOtherThan((int) DB::table('time_studies')->where('id', $leadId)->value('conducted_by_id'))));
                if ($outcome === 'reject') {
                    TimeStudyReviewRepository::decide($leadId, 'rejected', 'Operators were not at a steady pace. Re-time with experienced operators.');
                } elseif ($outcome !== 'wait') {
                    TimeStudyReviewRepository::decide($leadId, 'approved', 'Proposed SMV looks right against the line target.');
                    OperationSmvRepository::apply($leadId, $outcome === 'approve_style' ? 'style_operation' : 'operation');
                    $applied++;
                }
            } catch (GeneralException $e) {
                $this->say('  could not decide an SMV session: ' . $e->getMessage());
            }
        }
        Auth::guard()->setUser(User::findOrFail($this->admin->id));
        $this->stats['smv_sessions'] = count($sessions);
        $this->stats['smv_applied'] = $applied;
    }

    // ---------------------------------------------------------------------
    // Team plans
    // ---------------------------------------------------------------------

    private function buildTeamPlans(): void
    {
        $count = 0;
        foreach ($this->teams as $team) {
            $styles = array_values(array_filter($this->styles, fn ($s) => in_array($s['category'], $team['focus'], true) && in_array($team['factory'], $s['factories'], true)));
            if (!$styles) {
                continue;
            }
            $styles = $this->shuffle($styles);
            $windows = [
                ['completed', -80, -51], ['in_progress', -50, 8], ['planned', 9, 40],
            ];
            foreach ($windows as $i => [$status, $from, $to]) {
                $style = $styles[$i % count($styles)];
                $plan = [
                    'team_id' => $team['id'], 'style_id' => $style['id'], 'sequence_no' => $i + 1,
                    'planned_quantity' => mt_rand(30, 90) * 100,
                    'planned_start_date' => $this->today->copy()->addDays($from)->toDateString(),
                    'planned_end_date' => $this->today->copy()->addDays($to)->toDateString(),
                    'status' => $status, 'is_active' => true,
                ];
                if ($status !== 'planned') {
                    $plan['actual_start_date'] = $plan['planned_start_date'];
                }
                if ($status === 'completed') {
                    $plan['actual_end_date'] = $plan['planned_end_date'];
                }
                try {
                    TeamPlanRepository::createRec($plan);
                    $count++;
                } catch (GeneralException $e) {
                    $this->say("  skipped a plan for {$team['code']}: " . $e->getMessage());
                }
            }
        }
        $this->stats['team_plans'] = $count;
    }

    // ---------------------------------------------------------------------
    // Study plans
    // ---------------------------------------------------------------------

    /**
     * Two plans to look at: a running one (some pairs done, one partly done, one skipped, one held by another
     * analyst) and a draft that breaks its template's layout, so the "cannot start yet" message shows.
     */
    private function buildStudyPlans(): void
    {
        $team = collect($this->teams)->first(fn ($t) => $t['code'] === 'T01');
        $operators = array_slice($this->shuffle($team['operators']), 0, 8);
        $operations = array_slice($this->operationsByCategory['Tee shirt'], 0, 4);

        // Two operators on each of four operations (the template wants at least two per operation).
        $pairs = [];
        foreach ($operations as $i => $operationId) {
            foreach ([0, 1] as $k) {
                $pairs[] = ['operation_id' => $operationId, 'employee_id' => $operators[$i * 2 + $k]];
            }
        }
        $plan = Plans::create([
            'name' => 'Tee line, neck to hem (week 44)', 'factory_id' => $this->factory['AXN2'], 'template_id' => $this->templateIds['sewing'],
            'time_study_type' => 'interview_training', 'target_per_pair' => 2, 'pairs' => $pairs,
        ]);
        $plan = Plans::activate($plan['id']);

        $template = $plan['template'];
        $items = $plan['items'];
        $recorded = 0;
        foreach ($items as $index => $item) {
            // First two operations: both operators done. Third: one operator has one of two studies.
            $studies = $index < 4 ? 2 : ($index === 4 ? 1 : 0);
            for ($n = 0; $n < $studies; $n++) {
                $recorded += $this->recordPlannedStudy($item, $template) ? 1 : 0;
            }
        }
        Plans::skipPair($plan['id'], $items[7]['id'], 'Operator was moved to the cutting room');
        // Another analyst is timing the third operation's second operator right now.
        // The last user, so it is unlikely to be whoever is looking at the screen.
        $other = (int) end($this->reviewers);
        TimeStudyPlanItem::whereKey($items[5]['id'])->update(['claimed_by_id' => $other, 'claimed_until' => now()->addHours(12)]);

        // A draft that breaks its template: two operators on an operation that allows one.
        $style = collect($this->styles)->first(fn ($s) => $s['category'] === 'Polo tee' && in_array($this->factory['AXN2'], $s['factories'], true));
        $poloTeam = collect($this->teams)->first(fn ($t) => $t['code'] === 'T03');
        $poloOperators = array_slice($this->shuffle($poloTeam['operators']), 0, 4);
        $poloOperations = array_slice($this->operationsByCategory['Polo tee'], 1, 3);
        Plans::create([
            'name' => 'Polo collar and placket (draft)', 'factory_id' => $this->factory['AXN2'], 'template_id' => $this->templateIds['floor'],
            'time_study_type' => 'production_floor', 'style_id' => $style['id'], 'target_per_pair' => 1,
            'pairs' => [
                ['operation_id' => $poloOperations[0], 'employee_id' => $poloOperators[0]],
                ['operation_id' => $poloOperations[0], 'employee_id' => $poloOperators[1]],
                ['operation_id' => $poloOperations[1], 'employee_id' => $poloOperators[2]],
                ['operation_id' => $poloOperations[2], 'employee_id' => $poloOperators[3]],
            ],
        ]);

        $this->stats['study_plans'] = ['running' => 1, 'draft' => 1, 'planned_studies_recorded' => $recorded];
    }

    /** Records one study for a planned pair, through the same path the study screen uses. */
    private function recordPlannedStudy(array $item, array $template): bool
    {
        $operation = $this->operations[$item['operation_id']];
        $pair = ['employee' => $item['employee']['id'], 'operation' => $item['operation_id'], 'factory' => $this->factory['AXN2']];
        $date = $this->today->copy()->subDays(mt_rand(0, 3));
        $conductor = $this->reviewers[mt_rand(0, count($this->reviewers) - 1)];
        $active = ['version_id' => $template['version_id'], 'definition' => $template['definition']];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $payload = $this->studyPayload($pair, $operation, $date, 'interview_training', null, $active, false, $this->rand(58, 88), $this->effectiveSmv($item['operation_id'], null), $conductor, $attempt);
            $payload['time_study_plan_item_id'] = $item['id'];
            try {
                $study = TimeStudyRepository::createRec($payload);
                $stamp = $date->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59));
                DB::table('time_studies')->where('id', $study->id)->update(['created_at' => $stamp, 'updated_at' => $stamp]);
                $this->studyIds[] = (int) $study->id;

                return true;
            } catch (GeneralException $e) {
                continue;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // Combined cycles
    // ---------------------------------------------------------------------

    /**
     * An operator timed on two neighbouring operations one after another, as a single pass: three loose cycles and
     * a running plan (template with the "combined" layout) in which one operator's cycle is already recorded.
     */
    private function buildCycles(): void
    {
        $team = collect($this->teams)->first(fn ($t) => $t['code'] === 'T02');
        $operators = array_slice($this->shuffle($team['operators']), 0, 5);
        $teeOperations = array_slice($this->operationsByCategory['Tee shirt'], 0, 3);
        $station = Templates::startFrom($this->templateIds['station']);

        $recorded = 0;
        foreach ([[0, [0, 1]], [1, [1, 2]], [2, [0, 1, 2]]] as [$who, $indexes]) {
            $operations = array_map(fn ($i) => $teeOperations[$i], $indexes);
            $recorded += $this->recordCycle($operators[$who], $operations, $station, $this->today->copy()->subDays(mt_rand(1, 6)));
        }

        // A running plan: two operators, each on the same two operations, one cycle per operator.
        $pairs = [];
        foreach ([3, 4] as $who) {
            foreach (array_slice($teeOperations, 0, 2) as $operationId) {
                $pairs[] = ['operation_id' => $operationId, 'employee_id' => $operators[$who]];
            }
        }
        $plan = Plans::create([
            'name' => 'Neck and shoulder stations (cycles)', 'factory_id' => $this->factory['AXN2'], 'template_id' => $this->templateIds['station'],
            'time_study_type' => 'interview_training', 'target_per_pair' => 1, 'pairs' => $pairs,
        ]);
        $plan = Plans::activate($plan['id']);
        $firstOperatorItems = array_values(array_filter($plan['items'], fn ($i) => $i['employee']['id'] === $operators[3]));
        $recorded += $this->recordCycle($operators[3], array_column($firstOperatorItems, 'operation_id'), $station, $this->today->copy()->subDays(1), array_column($firstOperatorItems, 'id'));

        $this->stats['cycle_studies'] = $recorded;
    }

    /** Records one operator's cycle through the same path as the study screen; returns how many studies were saved. */
    private function recordCycle(int $employeeId, array $operationIds, array $template, Carbon $date, array $planItemIds = []): int
    {
        $rounds = mt_rand(8, 10);
        $efficiency = $this->rand(62, 88);
        $operations = [];
        foreach ($operationIds as $position => $operationId) {
            $operation = $this->operations[$operationId];
            $netMs = $operation['smv'] * 60000 / ($efficiency / 100);
            $laps = [];
            $downtimes = [];
            $down = 0;
            for ($n = 1; $n <= $rounds; $n++) {
                $net = (int) round($netMs * max(0.5, 1 + $this->normal(0, 0.06)));
                // Moving to this operation from the previous one takes a few seconds, recorded as down time on this lap.
                $change = $this->chance(0.9) ? mt_rand(3000, 9000) : 0;
                $laps[] = ['lap_no' => $n, 'duration_ms' => $net + $change, 'down_time_ms' => $change, 'net_ms' => $net, 'is_excluded' => false];
                if ($change > 0) {
                    $downtimes[] = ['lap_no' => $n, 'time_study_downtime_reason_id' => $this->reason['OPERATION_CHANGE'], 'duration_ms' => $change, 'note' => null];
                    $down += $change;
                }
            }
            $operations[] = array_filter([
                'operation_id' => $operationId, 'template_version_id' => $template['version_id'], 'template_overridden' => true,
                'time_study_plan_item_id' => $planItemIds[$position] ?? null, 'skill_ids' => $operation['skills'],
                'total_down_time_ms' => $down, 'total_hold_ms' => 0, 'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')),
                'laps' => $laps, 'downtimes' => $downtimes,
            ], fn ($value) => $value !== null);
        }

        try {
            $result = Cycles::create([
                'factory_id' => $this->factory['AXN2'], 'study_date' => $date->toDateString(), 'time_study_type' => 'interview_training',
                'employee_id' => $employeeId, 'conducted_by_id' => $this->reviewers[mt_rand(0, count($this->reviewers) - 1)], 'operations' => $operations,
            ]);
        } catch (GeneralException $e) {
            $this->say('  skipped a cycle: ' . $e->getMessage());

            return 0;
        }
        $stamp = $date->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59));
        DB::table('time_studies')->whereIn('id', array_column($result['studies'], 'id'))->update(['created_at' => $stamp, 'updated_at' => $stamp]);
        $this->studyIds = array_merge($this->studyIds, array_column($result['studies'], 'id'));

        return count($result['studies']);
    }

    // ---------------------------------------------------------------------
    // Skill matrix
    // ---------------------------------------------------------------------

    /**
     * Gap Analysis and Skill Depth read the latest saved matrix run for the factories the user has selected,
     * and a run belongs to one exact selection. Saves one for the selections most likely to be used; any
     * other selection is one click on "Recalculate" in Skill Matrix Insights.
     */
    private function buildMatrixRuns(): void
    {
        $all = DB::table('factories')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selections = [[$this->factory['AXN2']], [$this->factory['AXN3']], [$this->factory['AXN2'], $this->factory['AXN3']], $all];
        try {
            foreach ($selections as $ids) {
                FactoryContext::set($ids);
                SkillMatrixCalculationRepository::recalculate([], (int) $this->admin->id);
            }
        } finally {
            FactoryContext::reset();
        }
        $this->stats['matrix_runs'] = count($selections);
    }

    // ---------------------------------------------------------------------
    // Random helpers (all driven by mt_rand so a seed reproduces the dataset)
    // ---------------------------------------------------------------------

    private function rand(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    private function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    private function normal(float $mean, float $sd): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function pick(array $items)
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function weighted(array $items, array $weights)
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($items as $i => $item) {
            $roll -= $weights[$i];
            if ($roll <= 0) {
                return $item;
            }
        }

        return end($items);
    }

    private function shuffle(array $items): array
    {
        shuffle($items);

        return array_values($items);
    }
}
