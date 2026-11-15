<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Loads the standard NPI Critical Path T&A list from "IE System Requirement_6106.xlsx" (sheet
// "NPI Critical Path"). This is reference data the app cannot work without, so it ships as a
// migration (like 2026_10_25_000006_seed_legacy_time_study_template) rather than a dev-only seeder.
//
// - Spelling is corrected from the sheet (Embilishment/Emblishment -> Embellishment, Reviw -> Review,
//   Reveive -> Receive, "Emblis. inhouse" -> "Embellishment In-house").
// - designation_id is left NULL on purpose: the responsibility text from the sheet goes to
//   responsibility_note and planners map each action to a real designation on the T&A Master page.
// - process_time is kept exactly as written (free text). NULL where the sheet is blank.
return new class extends Migration
{
    private const DEVELOPMENT = 'Development';
    private const PLANT_QCO = 'Plant QCO';

    /** [name, responsibility (as in the sheet), process time] */
    private function development(): array
    {
        return [
            ['Pilot RM In-house Date', 'MANAGER-RMWH', '-1'],
            ['GRN/Inspection', 'MANAGER-RMWH', '-1'],
            ['Prepare and Send RM Pack (Fab & Trims)', 'MANAGER-RMWH', '0'],
            ['Fabric Relaxing For Ranala Pilots', 'MANAGER-RMWH', '0'],
            ['PHD (Style Handover from PD to PE)', 'AM-Garment Tech', '-1'],
            ['Pattern Check', 'PE-Technical', '-1'],
            ['PE/Pilot PCD', 'PDC-Cutting Lead', '0'],
            ['Embellishment Send', 'PDC-Embellishment', '-4'],
            ['Embellishment Receive', 'PDC-Embellishment', '-1'],
            ['PE/Pilot PSD', 'Manager-Tec PD/PE', '6,S-14'],
            ['PE/Pilot PED', 'Manager-Tec PD/PE', '0'],
            ['Wash/Dye Send', 'PDC-Embellishment', '3,5'],
            ['Wash/Dye Receiving', 'PDC-Embellishment', '-1'],
            ['After Wash Operation', 'Manager-Tec PD/PE', '-1'],
            ['Size Set Review (PE)', 'Manager-Tec PD/PE', '-1'],
            ['Send PE Package to Plant PE (Size Set)', 'Manager-Tec PD/PE', 'H-0,(G,P,M)-1,OS-5'],
            ['Receive PE Package at Plant PE', 'Manager-Tec PD/PE', '-1'],
            ['Trial Cut Receive at Plant', 'Manager-Tec Plant', '0'],
            ['PE Meeting', 'Manager-Tec Plant', '-1'],
            ['Send PE Document Package to Plant (Without STW)', 'Manager-Tec PD/PE', '0'],
            ['TLT / Trial PCD', 'Manager-Tec Plant', '-1'],
            ['TLT / Trial PSD', 'Manager-Tec Plant', '-4'],
            ['TLT / Trial PED', 'Manager-Tec Plant', '0'],
            ['TLT Capability - Brand Review', 'Manager-Tec Plant', '0'],
            ['Wash Size Set Cut', 'Manager-Cutting', '-1'],
            ['Embellishment Send Date', 'Executive-EMB', '0'],
            ['Embellishment In-house Date', 'Executive-EMB', '0'],
            ['Wash Size Set Stitch', 'Manager-Tec Plant', '-2'],
            ['Hand Over to Wash', 'Executive-EMB', '-8'],
            ['Receiving from Wash', 'Executive-EMB', '-2'],
            ['Inform Pattern Adjustment', 'Manager-Tec Plant', '-1'],
            ['Patterns Alter', 'Manager-Tec PD/PE', '-1'],
            ['PP Meeting', 'Manager-Tec Plant', '-1'],
            ['BULK PCD', 'Manager-Cutting', '-1'],
            ['BULK PSD', 'Manager-Operation', '0'],
            ['Handover Meeting - For Direct Bulk Styles', 'AM-Garment Tech', null],
        ];
    }

    private function plantQco(): array
    {
        return [
            ['RA Meeting (Early Engagement)', 'Technical M', 'Presign off'],
            ['4M Analysis (Pre Signoff Meeting)', 'QCO IE', 'Presign off'],
            ['QCO Material Requirement', 'QCO IE', 'Presign off +1day'],
            ['Received Tentative OBD', 'QCO IE', 'Presign off +1day'],
            ['Identify Critical Operations & Special MCs (RA Feedbacks)', 'Technical M', 'Presign off +2day'],
            ['Machine Requirement', 'QCO IE', 'Presign off +2day'],
            ['Machine Allocation Plan (Internal/External)', 'Plant Engineer', 'Presign off +3day'],
            ['Attachment Availability (Folders/Jig Board/Templates/Extensions, etc.)', 'Plant Engineer', 'Presign off +3day'],
            ['Machine Layout Verification', 'IEM', '8'],
            ['PE Hand Over', 'Technical M', '8'],
            ['Internal PP with Size Set Comments', 'Technical M', '8'],
            ['STW Availability', 'QCO IE / PE IE', '6'],
            ['MO Allocation', 'IEM', '6'],
            ['Skill Gap Analysis', 'IEM', '6'],
            ['Training Plan', 'QCO IE', '6'],
            ['Machine Availability', 'Plant Engineer', '5'],
            ['Training Cut Availability', 'Cutting M', '5'],
            ['Thread Minimum', 'IEM', '4'],
            ['Patterns Review (Cut Panel)', 'Technical M', '4'],
            ['Machine Presetting Start Date', 'Plant Engineer / Technical M', '4'],
            ['Team Member Training Start Date', 'Technical M', '4'],
            ['Bulk Cutting', 'Cutting M', '3'],
            ['Machine Presetting End Date', 'Plant Engineer / Technical M', '1'],
            ['Team Member Training End Date', 'Technical M', '1'],
            ['Feeding Plan', 'QCO IE', '1'],
            ['QCO Handover Meeting', 'QCO IE', '1'],
            ['First 10 Pcs Approval', 'Quality M', '0'],
            ['QCO and Feeding Final Review', 'QCO IE', 'Weekly'],
            ['Size Set Package', 'Technical M/PE', null],
            ['Size Set Completion', 'Technical M', null],
            ['Size Set Approval', 'Quality M', null],
            ['Technical Trial', 'Technical M', null],
            ['Technical Trial Approval', 'Quality M', null],
            ['Customer PP', 'Quality M/PP Coordinator', null],
        ];
    }

    public function up()
    {
        $now = now();
        $rows = [];
        foreach ([self::DEVELOPMENT => $this->development(), self::PLANT_QCO => $this->plantQco()] as $phase => $actions) {
            foreach ($actions as $index => [$name, $responsibility, $processTime]) {
                $rows[] = [
                    'phase' => $phase,
                    'sequence' => $index + 1,
                    'name' => $name,
                    'designation_id' => null,
                    'responsibility_note' => $responsibility,
                    'process_time' => $processTime,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // insertOrIgnore: re-running on a DB that already has some of these rows must not fail on the
        // (phase, name) unique key.
        DB::table('ta_template_actions')->insertOrIgnore($rows);
    }

    public function down()
    {
        // Only the rows this migration created (no author); actions planners added are kept.
        DB::table('ta_template_actions')->whereNull('created_by_id')->delete();
    }
};
