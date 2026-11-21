<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives every standard T&A action a responsible designation (and every such designation a department),
 * taken from the "Responsibility" column of the source sheet ("IE System Requirement_6106.xlsx", sheet
 * "NPI Critical Path").
 *
 * This is a SEEDER, not a migration, on purpose: designations and departments are each organisation's own
 * master data, so they must not appear automatically on every deploy. Run it deliberately where wanted:
 *   php artisan db:seed --class="Database\Seeders\TaDesignationsSeeder"
 * or leave it out and let planners map the actions on the T&A Master page.
 *
 * Designations — for each distinct responsibility it first looks for an EXISTING designation with the same
 * name (case, spacing and punctuation ignored, plus a few obvious aliases such as "Quality M" = "Quality
 * Manager") and reuses it; only when none exists is one created. A name matching several existing
 * designations is left alone (warned) rather than guessed.
 * Departments — a designation WITHOUT a department gets one: an existing department whose name clearly
 * matches (whole words) when exactly one does, else a new one. Several matches = left blank (warned).
 * The groupings are a judgement call from the sheet's role names: review them on the Departments screen.
 * Mapping — only standard actions with no designation are filled, and only designations/departments that are
 * blank are filled: anything set by hand is never changed. Where the sheet names two roles for one action
 * ("QCO IE / PE IE", "Plant Engineer / Technical M", "Technical M/PE", "Quality M/PP Coordinator") the FIRST
 * role is used; the second is not recorded.
 * Style T&A rows that were applied from the standard list before it was mapped (entered in the app, so they
 * have an author) are filled from their action; rows that have a designation are untouched.
 *
 * Safe to re-run: it reuses what it created before.
 */
class TaDesignationsSeeder extends Seeder
{
    private int $createdDesignations = 0;
    private int $reusedDesignations = 0;
    private int $createdDepartments = 0;
    private int $placedDesignations = 0;
    private int $mappedActions = 0;
    private int $filledStyleRows = 0;
    /** @var string[] */
    private array $warnings = [];

    /** key => [name to create, other names that count as the same designation, department key] */
    private function designations(): array
    {
        return [
            'RMWH' => ['Manager - RMWH', ['MANAGER-RMWH', 'Manager RMWH', 'Manager - Raw Material Warehouse'], 'WAREHOUSE'],
            'AMGT' => ['AM - Garment Tech', ['AM-Garment Tech', 'AM Garment Tech', 'Assistant Manager - Garment Technology'], 'PRODDEV'],
            'PETECH' => ['PE - Technical', ['PE-Technical', 'PE Technical'], 'PRODDEV'],
            'PDCCUT' => ['PDC - Cutting Lead', ['PDC-Cutting Lead', 'PDC Cutting Lead'], 'PRODDEV'],
            'PDCEMB' => ['PDC - Embellishment', ['PDC-Embellishment', 'PDC-Emblishment', 'PDC Embellishment'], 'PRODDEV'],
            'MTPDPE' => ['Manager - Tec PD/PE', ['Manager-Tec PD/PE', 'Manager Tec PD PE', 'Manager - Technical PD/PE'], 'PRODDEV'],
            'MTPLANT' => ['Manager - Tec Plant', ['Manager-Tec Plant', 'Manager Tec Plant', 'Manager - Technical Plant'], 'TECHNICAL'],
            'CUTM' => ['Cutting Manager', ['Manager-Cutting', 'Manager - Cutting', 'Cutting M'], 'CUTTING'],
            'EXEMB' => ['Executive - EMB', ['Executive-EMB', 'Executive EMB', 'Executive - Embellishment'], 'EMBELLISHMENT'],
            'OPSM' => ['Manager - Operation', ['Manager-Operation', 'Operations Manager', 'Operation Manager'], 'OPERATIONS'],
            'TECHM' => ['Technical Manager', ['Technical M', 'Tech Manager', 'Technical Mgr'], 'TECHNICAL'],
            'QCOIE' => ['QCO IE', ['QCO IE'], 'IE'],
            'PLANTENG' => ['Plant Engineer', ['Plant Engineer'], 'TECHNICAL'],
            'IEM' => ['IE Manager', ['IEM', 'Industrial Engineering Manager', 'Industrial Engineer Manager'], 'IE'],
            'QUALM' => ['Quality Manager', ['Quality M', 'Quality Mgr', 'QA Manager'], 'QUALITY'],
        ];
    }

    /** key => [name to create, whole words that mean "this department" when found in an existing name] */
    private function departments(): array
    {
        return [
            // Not a bare "warehouse": a "Finished Goods Warehouse" is not the raw-material one.
            'WAREHOUSE' => ['Raw Material Warehouse', ['rmwh', 'rm warehouse', 'raw material']],
            'PRODDEV' => ['Product Development', ['product development', 'pdc']],
            'TECHNICAL' => ['Technical', ['technical']],
            'CUTTING' => ['Cutting', ['cutting']],
            'EMBELLISHMENT' => ['Embellishment', ['embellishment', 'emblishment', 'emb']],
            'OPERATIONS' => ['Operations', ['operation', 'operations']],
            'IE' => ['Industrial Engineering', ['industrial engineering', 'industrial engineer', 'ie', 'i e']],
            'QUALITY' => ['Quality', ['quality', 'qa', 'qc']],
        ];
    }

    /** [phase, action name as loaded by migration 2026_11_15_000003, designation key] */
    private function mapping(): array
    {
        return [
            ['Development', 'Pilot RM In-house Date', 'RMWH'],
            ['Development', 'GRN/Inspection', 'RMWH'],
            ['Development', 'Prepare and Send RM Pack (Fab & Trims)', 'RMWH'],
            ['Development', 'Fabric Relaxing For Ranala Pilots', 'RMWH'],
            ['Development', 'PHD (Style Handover from PD to PE)', 'AMGT'],
            ['Development', 'Pattern Check', 'PETECH'],
            ['Development', 'PE/Pilot PCD', 'PDCCUT'],
            ['Development', 'Embellishment Send', 'PDCEMB'],
            ['Development', 'Embellishment Receive', 'PDCEMB'],
            ['Development', 'PE/Pilot PSD', 'MTPDPE'],
            ['Development', 'PE/Pilot PED', 'MTPDPE'],
            ['Development', 'Wash/Dye Send', 'PDCEMB'],
            ['Development', 'Wash/Dye Receiving', 'PDCEMB'],
            ['Development', 'After Wash Operation', 'MTPDPE'],
            ['Development', 'Size Set Review (PE)', 'MTPDPE'],
            ['Development', 'Send PE Package to Plant PE (Size Set)', 'MTPDPE'],
            ['Development', 'Receive PE Package at Plant PE', 'MTPDPE'],
            ['Development', 'Trial Cut Receive at Plant', 'MTPLANT'],
            ['Development', 'PE Meeting', 'MTPLANT'],
            ['Development', 'Send PE Document Package to Plant (Without STW)', 'MTPDPE'],
            ['Development', 'TLT / Trial PCD', 'MTPLANT'],
            ['Development', 'TLT / Trial PSD', 'MTPLANT'],
            ['Development', 'TLT / Trial PED', 'MTPLANT'],
            ['Development', 'TLT Capability - Brand Review', 'MTPLANT'],
            ['Development', 'Wash Size Set Cut', 'CUTM'],
            ['Development', 'Embellishment Send Date', 'EXEMB'],
            ['Development', 'Embellishment In-house Date', 'EXEMB'],
            ['Development', 'Wash Size Set Stitch', 'MTPLANT'],
            ['Development', 'Hand Over to Wash', 'EXEMB'],
            ['Development', 'Receiving from Wash', 'EXEMB'],
            ['Development', 'Inform Pattern Adjustment', 'MTPLANT'],
            ['Development', 'Patterns Alter', 'MTPDPE'],
            ['Development', 'PP Meeting', 'MTPLANT'],
            ['Development', 'BULK PCD', 'CUTM'],
            ['Development', 'BULK PSD', 'OPSM'],
            ['Development', 'Handover Meeting - For Direct Bulk Styles', 'AMGT'],
            ['Plant QCO', 'RA Meeting (Early Engagement)', 'TECHM'],
            ['Plant QCO', '4M Analysis (Pre Signoff Meeting)', 'QCOIE'],
            ['Plant QCO', 'QCO Material Requirement', 'QCOIE'],
            ['Plant QCO', 'Received Tentative OBD', 'QCOIE'],
            ['Plant QCO', 'Identify Critical Operations & Special MCs (RA Feedbacks)', 'TECHM'],
            ['Plant QCO', 'Machine Requirement', 'QCOIE'],
            ['Plant QCO', 'Machine Allocation Plan (Internal/External)', 'PLANTENG'],
            ['Plant QCO', 'Attachment Availability (Folders/Jig Board/Templates/Extensions, etc.)', 'PLANTENG'],
            ['Plant QCO', 'Machine Layout Verification', 'IEM'],
            ['Plant QCO', 'PE Hand Over', 'TECHM'],
            ['Plant QCO', 'Internal PP with Size Set Comments', 'TECHM'],
            ['Plant QCO', 'STW Availability', 'QCOIE'],
            ['Plant QCO', 'MO Allocation', 'IEM'],
            ['Plant QCO', 'Skill Gap Analysis', 'IEM'],
            ['Plant QCO', 'Training Plan', 'QCOIE'],
            ['Plant QCO', 'Machine Availability', 'PLANTENG'],
            ['Plant QCO', 'Training Cut Availability', 'CUTM'],
            ['Plant QCO', 'Thread Minimum', 'IEM'],
            ['Plant QCO', 'Patterns Review (Cut Panel)', 'TECHM'],
            ['Plant QCO', 'Machine Presetting Start Date', 'PLANTENG'],
            ['Plant QCO', 'Team Member Training Start Date', 'TECHM'],
            ['Plant QCO', 'Bulk Cutting', 'CUTM'],
            ['Plant QCO', 'Machine Presetting End Date', 'PLANTENG'],
            ['Plant QCO', 'Team Member Training End Date', 'TECHM'],
            ['Plant QCO', 'Feeding Plan', 'QCOIE'],
            ['Plant QCO', 'QCO Handover Meeting', 'QCOIE'],
            ['Plant QCO', 'First 10 Pcs Approval', 'QUALM'],
            ['Plant QCO', 'QCO and Feeding Final Review', 'QCOIE'],
            ['Plant QCO', 'Size Set Package', 'TECHM'],
            ['Plant QCO', 'Size Set Completion', 'TECHM'],
            ['Plant QCO', 'Size Set Approval', 'QUALM'],
            ['Plant QCO', 'Technical Trial', 'TECHM'],
            ['Plant QCO', 'Technical Trial Approval', 'QUALM'],
            ['Plant QCO', 'Customer PP', 'QUALM'],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            $now = now();
            $departments = DB::table('departments')->get(['id', 'name']);
            $designations = DB::table('designations')->get(['id', 'name', 'department_id']);

            $resolved = [];      // designation key => id|null
            $departmentIds = []; // department key => id|null, resolved lazily so unused ones are never created
            foreach ($this->designations() as $key => [$name, $aliases, $departmentKey]) {
                $designation = $this->findOrCreateDesignation($designations, $name, $aliases, $now);
                $resolved[$key] = $designation ? (int) $designation->id : null;
                if (!$designation || $designation->department_id !== null) {
                    continue; // missing/ambiguous, or already placed by hand — never overridden
                }

                if (!array_key_exists($departmentKey, $departmentIds)) {
                    $departmentIds[$departmentKey] = $this->findOrCreateDepartment($departments, $departmentKey, $now);
                }
                if ($departmentIds[$departmentKey] !== null) {
                    DB::table('designations')->where('id', $designation->id)->whereNull('department_id')
                        ->update(['department_id' => $departmentIds[$departmentKey]]);
                    $designation->department_id = $departmentIds[$departmentKey];
                    $this->placedDesignations++;
                }
            }

            // Only unmapped actions: a designation chosen by hand on the T&A Master page is never replaced.
            foreach ($this->mapping() as [$phase, $action, $key]) {
                if (($resolved[$key] ?? null) === null) {
                    continue;
                }
                $this->mappedActions += DB::table('ta_template_actions')
                    ->where('phase', $phase)->where('name', $action)->whereNull('designation_id')
                    ->update(['designation_id' => $resolved[$key]]);
            }

            // Style actions applied before the list was mapped (rows entered in the app have an author).
            $this->filledStyleRows = DB::table('style_ta_actions')
                ->join('ta_template_actions', 'ta_template_actions.id', '=', 'style_ta_actions.ta_template_action_id')
                ->whereNull('style_ta_actions.designation_id')
                ->whereNotNull('style_ta_actions.created_by_id')
                ->whereNotNull('ta_template_actions.designation_id')
                ->update(['style_ta_actions.designation_id' => DB::raw('ta_template_actions.designation_id')]);
        });

        $this->report();
    }

    /** @return object|null the designation row, or null when the name is ambiguous among existing designations */
    private function findOrCreateDesignation($designations, string $name, array $aliases, $now): ?object
    {
        $accepted = [];
        foreach (array_merge([$name], $aliases) as $candidate) {
            $accepted[$this->normalise($candidate)] = true;
        }

        $matches = $designations->filter(fn ($d) => isset($accepted[$this->normalise($d->name)]));
        if ($matches->count() === 1) {
            $this->reusedDesignations++;

            return $matches->first();
        }
        if ($matches->count() > 1) {
            $this->warn('Designation "'.$name.'" matches several existing designations ('.$matches->pluck('name')->implode(', ').') — map those actions by hand.');

            return null;
        }

        $row = (object) [
            'id' => DB::table('designations')->insertGetId([
                'name' => $name,
                'code' => $this->uniqueCode('designations', $name),
                'department_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'name' => $name,
            'department_id' => null,
        ];
        $designations->push($row);
        $this->createdDesignations++;

        return $row;
    }

    /** @return int|null null when several existing departments match (never guess between them) */
    private function findOrCreateDepartment($departments, string $key, $now): ?int
    {
        [$name, $keywords] = $this->departments()[$key];

        $matches = $departments->filter(function ($department) use ($keywords) {
            $haystack = ' '.$this->normalise($department->name).' ';
            foreach ($keywords as $keyword) {
                if (strpos($haystack, ' '.$this->normalise($keyword).' ') !== false) {
                    return true;
                }
            }

            return false;
        });

        if ($matches->count() === 1) {
            return (int) $matches->first()->id;
        }
        if ($matches->count() > 1) {
            $this->warn('Department "'.$name.'": several existing departments match ('.$matches->pluck('name')->implode(', ').') — set it by hand on those designations.');

            return null;
        }

        $id = DB::table('departments')->insertGetId([
            'name' => $name,
            'code' => $this->uniqueCode('departments', $name),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $departments->push((object) ['id' => $id, 'name' => $name]);
        $this->createdDepartments++;

        return (int) $id;
    }

    /** Case, spacing and punctuation are ignored: "MANAGER-RMWH", "Manager - RMWH" and "manager rmwh" match. */
    private function normalise(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    /** "Manager - RMWH" -> MANAGER-RMWH; a numeric suffix keeps it unique (codes are unique, max 50). */
    private function uniqueCode(string $table, string $name): string
    {
        $base = substr(trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)), '-'), 0, 44);
        $code = $base;
        for ($n = 2; DB::table($table)->where('code', $code)->exists(); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
        Log::warning('TaDesignationsSeeder: '.$message);
    }

    private function report(): void
    {
        if (!$this->command) {
            return;
        }

        $this->command->info(sprintf(
            'T&A designations: %d created, %d existing reused; departments: %d created, %d designations placed; %d standard actions mapped; %d style rows filled.',
            $this->createdDesignations,
            $this->reusedDesignations,
            $this->createdDepartments,
            $this->placedDesignations,
            $this->mappedActions,
            $this->filledStyleRows
        ));
        foreach ($this->warnings as $warning) {
            $this->command->warn('  '.$warning);
        }
    }
}
