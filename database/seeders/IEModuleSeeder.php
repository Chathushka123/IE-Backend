<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Real-world IE Module dataset: Lingerie & Activewear factory.
 *
 * Builds on existing master data. Safe to run multiple times —
 * insertOrIgnore() skips rows whose unique key already exists.
 *
 * Run: php artisan db:seed --class="Database\Seeders\IEModuleSeeder"
 */
class IEModuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $this->seedMachineCategories();
        $this->seedMachineTypes();
        $this->seedOperationCategories();
        $this->seedSkills();
        $this->seedOperations();
        $this->seedOperationGradings();
        $this->seedProducts();
        $this->seedProductOperationGradings();

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->command->info('IE Module seed complete.');
    }

    // ─────────────────────────────────────────────────────────────
    // 1. MACHINE CATEGORIES
    // ─────────────────────────────────────────────────────────────
    private function seedMachineCategories(): void
    {
        DB::table('machine_categories')->insertOrIgnore([
            ['description' => 'Cutting Equipment',   'code' => 'CUT-EQ', 'is_active' => 1],
            ['description' => 'Pressing & Fusing',   'code' => 'PRS-EQ', 'is_active' => 1],
            ['description' => 'Inspection Equipment','code' => 'INS-EQ', 'is_active' => 1],
            ['description' => 'Embroidery',          'code' => 'EMB-EQ', 'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. MACHINE TYPES
    // ─────────────────────────────────────────────────────────────
    private function seedMachineTypes(): void
    {
        $catId = fn(string $desc) => DB::table('machine_categories')
            ->where('description', $desc)->value('id');

        $cuttingCat  = $catId('Cutting Equipment');
        $pressCat    = $catId('Pressing & Fusing');
        $insCat      = $catId('Inspection Equipment');
        $sewCat      = DB::table('machine_categories')->where('description', 'LIKE', '%Sewing%')->value('id')
                       ?? DB::table('machine_categories')->where('code', 'BSM')->value('id');
        $finishCat   = DB::table('machine_categories')->where('description', 'LIKE', '%Specialized%')->value('id')
                       ?? $sewCat;

        DB::table('machine_types')->insertOrIgnore([
            // Cutting
            ['description' => 'Straight Knife Cutter',   'code' => 'SKC',  'machine_category_id' => $cuttingCat, 'is_active' => 1],
            ['description' => 'Band Knife Machine',       'code' => 'BNK',  'machine_category_id' => $cuttingCat, 'is_active' => 1],
            ['description' => 'Rotary Cutter',            'code' => 'RTC',  'machine_category_id' => $cuttingCat, 'is_active' => 1],
            // Pressing & Fusing
            ['description' => 'Steam Iron',               'code' => 'STI',  'machine_category_id' => $pressCat,   'is_active' => 1],
            ['description' => 'Buck Press',               'code' => 'BKP',  'machine_category_id' => $pressCat,   'is_active' => 1],
            ['description' => 'Tunnel Finisher',          'code' => 'TNF',  'machine_category_id' => $pressCat,   'is_active' => 1],
            ['description' => 'Fusing Machine',           'code' => 'FSM',  'machine_category_id' => $pressCat,   'is_active' => 1],
            // Inspection
            ['description' => 'Needle Detector',          'code' => 'NDD',  'machine_category_id' => $insCat,     'is_active' => 1],
            ['description' => 'Measurement Table',        'code' => 'MST',  'machine_category_id' => $insCat,     'is_active' => 1],
            // Finishing / Manual
            ['description' => 'Manual Operation Station', 'code' => 'MNL',  'machine_category_id' => $finishCat,  'is_active' => 1],
            ['description' => 'Thread Trimmer',           'code' => 'TTR',  'machine_category_id' => $finishCat,  'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. BASE OPERATION CATEGORIES
    // ─────────────────────────────────────────────────────────────
    private function seedOperationCategories(): void
    {
        DB::table('base_operation_categories')->insertOrIgnore([
            ['description' => 'Cutting Operations',      'code' => 'CAT-CUT', 'is_active' => 1],
            ['description' => 'Body Seaming',            'code' => 'CAT-SEA', 'is_active' => 1],
            ['description' => 'Lingerie Attachment',     'code' => 'CAT-ATT', 'is_active' => 1],
            ['description' => 'Pressing Operations',     'code' => 'CAT-PRS', 'is_active' => 1],
            ['description' => 'Quality & Inspection',    'code' => 'CAT-QCI', 'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. SKILLS
    // ─────────────────────────────────────────────────────────────
    private function seedSkills(): void
    {
        DB::table('skills')->insertOrIgnore([
            ['description' => 'Bartack Machine Operation', 'code' => 'SK-BTK', 'is_active' => 1],
            ['description' => 'Fabric Cutting Skill',      'code' => 'SK-CUT', 'is_active' => 1],
            ['description' => 'Steam Pressing Skill',      'code' => 'SK-PRS', 'is_active' => 1],
            ['description' => 'Fusing Machine Skill',      'code' => 'SK-FUS', 'is_active' => 1],
            ['description' => 'Lace Attachment Skill',     'code' => 'SK-LAC', 'is_active' => 1],
            ['description' => 'Label Attachment Skill',    'code' => 'SK-LBL', 'is_active' => 1],
            ['description' => 'Needle Detection Skill',    'code' => 'SK-NDD', 'is_active' => 1],
            ['description' => 'Pattern Matching Skill',    'code' => 'SK-PTN', 'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. BASE OPERATIONS
    // ─────────────────────────────────────────────────────────────
    private function seedOperations(): void
    {
        $catId = fn(string $desc) => DB::table('base_operation_categories')
            ->where('description', $desc)->value('id');

        $cutCat  = $catId('Cutting Operations');
        $preCat  = DB::table('base_operation_categories')->where('description', 'Preparation Operations')->value('id');
        $asmCat  = DB::table('base_operation_categories')->where('description', 'Assembly Operations')->value('id');
        $seaCat  = $catId('Body Seaming');
        $hemCat  = DB::table('base_operation_categories')->where('description', 'Bottom/Hem Operations')->value('id');
        $attCat  = $catId('Lingerie Attachment');
        $prsCat  = $catId('Pressing Operations');
        $qciCat  = $catId('Quality & Inspection');

        DB::table('base_operations')->insertOrIgnore([
            // Cutting
            ['description' => 'Fabric Laying',            'code' => 'OP-C01', 'base_operation_category_id' => $cutCat, 'is_active' => 1],
            ['description' => 'Straight Knife Cutting',   'code' => 'OP-C02', 'base_operation_category_id' => $cutCat, 'is_active' => 1],
            ['description' => 'Band Knife Cutting',       'code' => 'OP-C03', 'base_operation_category_id' => $cutCat, 'is_active' => 1],
            ['description' => 'Notching',                 'code' => 'OP-C04', 'base_operation_category_id' => $cutCat, 'is_active' => 1],
            ['description' => 'Numbering and Bundling',   'code' => 'OP-C05', 'base_operation_category_id' => $cutCat, 'is_active' => 1],
            // Preparation
            ['description' => 'Front Panel Fusing',       'code' => 'OP-P01', 'base_operation_category_id' => $preCat, 'is_active' => 1],
            ['description' => 'Waistband Fusing',         'code' => 'OP-P02', 'base_operation_category_id' => $preCat, 'is_active' => 1],
            ['description' => 'Interlining Application',  'code' => 'OP-P03', 'base_operation_category_id' => $preCat, 'is_active' => 1],
            // Assembly
            ['description' => 'Cup Assembly',             'code' => 'OP-A01', 'base_operation_category_id' => $asmCat, 'is_active' => 1],
            ['description' => 'Gusset Attach',            'code' => 'OP-A02', 'base_operation_category_id' => $asmCat, 'is_active' => 1],
            ['description' => 'Liner Attach',             'code' => 'OP-A03', 'base_operation_category_id' => $asmCat, 'is_active' => 1],
            ['description' => 'Band Attach',              'code' => 'OP-A04', 'base_operation_category_id' => $asmCat, 'is_active' => 1],
            ['description' => 'Front Back Panel Join',    'code' => 'OP-A05', 'base_operation_category_id' => $asmCat, 'is_active' => 1],
            // Body Seaming
            ['description' => 'Inseam Sewing',            'code' => 'OP-S01', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Outseam Sewing',           'code' => 'OP-S02', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Crotch Seam',              'code' => 'OP-S03', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Back Seam',                'code' => 'OP-S04', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Front Rise Seam',          'code' => 'OP-S05', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Armhole Seam',             'code' => 'OP-S06', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Neckline Seam',            'code' => 'OP-S07', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Underwire Casing Seam',    'code' => 'OP-S08', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Bridge Seam',              'code' => 'OP-S09', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            ['description' => 'Wing Seam',                'code' => 'OP-S10', 'base_operation_category_id' => $seaCat, 'is_active' => 1],
            // Hemming
            ['description' => 'Leg Hem',                  'code' => 'OP-H01', 'base_operation_category_id' => $hemCat, 'is_active' => 1],
            ['description' => 'Waist Hem',                'code' => 'OP-H02', 'base_operation_category_id' => $hemCat, 'is_active' => 1],
            ['description' => 'Neck Hem',                 'code' => 'OP-H03', 'base_operation_category_id' => $hemCat, 'is_active' => 1],
            ['description' => 'Coverstitch Leg Hem',      'code' => 'OP-H04', 'base_operation_category_id' => $hemCat, 'is_active' => 1],
            ['description' => 'Shoulder Hem',             'code' => 'OP-H05', 'base_operation_category_id' => $hemCat, 'is_active' => 1],
            // Attachment
            ['description' => 'Elastic Attach Waist',     'code' => 'OP-AT1', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            ['description' => 'Elastic Attach Leg',       'code' => 'OP-AT2', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            ['description' => 'Lace Trim Attach',         'code' => 'OP-AT3', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            ['description' => 'Underwire Insert',         'code' => 'OP-AT4', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            ['description' => 'Strap Attach',             'code' => 'OP-AT5', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            ['description' => 'Clasp Attach',             'code' => 'OP-AT6', 'base_operation_category_id' => $attCat, 'is_active' => 1],
            // Pressing
            ['description' => 'Final Pressing',           'code' => 'OP-PR1', 'base_operation_category_id' => $prsCat, 'is_active' => 1],
            ['description' => 'Cup Pressing',             'code' => 'OP-PR2', 'base_operation_category_id' => $prsCat, 'is_active' => 1],
            ['description' => 'Seam Pressing',            'code' => 'OP-PR3', 'base_operation_category_id' => $prsCat, 'is_active' => 1],
            // Quality & Inspection
            ['description' => 'In-line Inspection',       'code' => 'OP-Q01', 'base_operation_category_id' => $qciCat, 'is_active' => 1],
            ['description' => 'Final Inspection',         'code' => 'OP-Q02', 'base_operation_category_id' => $qciCat, 'is_active' => 1],
            ['description' => 'Needle Detection Scan',    'code' => 'OP-Q03', 'base_operation_category_id' => $qciCat, 'is_active' => 1],
            ['description' => 'Measurement Check',        'code' => 'OP-Q04', 'base_operation_category_id' => $qciCat, 'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 6. OPERATIONS (was OPERATION GRADINGS)
    // ─────────────────────────────────────────────────────────────
    private function seedOperationGradings(): void
    {
        // Helpers to look up IDs by description
        $op  = fn(string $d) => DB::table('base_operations')->where('description', $d)->value('id');
        $mt  = fn(string $d) => DB::table('machine_types')->where('description', $d)->value('id');
        $gr  = fn(string $c) => DB::table('operation_grades')->where('code', $c)->value('id');

        // Machine type IDs (existing + new)
        $SNLS  = $mt('Single Needle Lockstitch Machine');
        $OVL5  = $mt('5 Thread Overlock');
        $OVL3  = $mt('3 Thread Overlock');
        $FLK   = $mt('Flatlock Machine');
        $BTK   = $mt('Bartack Machine');
        $LAM   = $mt('Label Attaching Machine');
        $EAM   = $mt('Elastic Attaching Machine');
        $HEM   = $mt('Hook & Eye Attaching Machine');
        $SKC   = $mt('Straight Knife Cutter');
        $STI   = $mt('Steam Iron');
        $FSM   = $mt('Fusing Machine');
        $NDD   = $mt('Needle Detector');
        $MST   = $mt('Measurement Table');
        $MNL   = $mt('Manual Operation Station');

        // Grade IDs (using existing codes A-F)
        $GRA = $gr('A'); // Simple/Trainee
        $GRB = $gr('B'); // Basic
        $GRC = $gr('C'); // Intermediate/Standard
        $GRD = $gr('D'); // Advanced/Senior
        $GRE = $gr('E'); // Expert

        // Operation IDs
        $oFabricLaying     = $op('Fabric Laying');
        $oStraightCut      = $op('Straight Knife Cutting');
        $oFrontPanelFusing = $op('Front Panel Fusing');
        $oWaistbandFusing  = $op('Waistband Fusing');
        $oCupAssembly      = $op('Cup Assembly');
        $oGussetAttach     = $op('Gusset Attach');
        $oLinerAttach      = $op('Liner Attach');
        $oBandAttach       = $op('Band Attach');
        $oFBPanelJoin      = $op('Front Back Panel Join');
        $oInseam           = $op('Inseam Sewing');
        $oOutseam          = $op('Outseam Sewing');
        $oCrotchSeam       = $op('Crotch Seam');
        $oBackSeam         = $op('Back Seam');
        $oFrontRise        = $op('Front Rise Seam');
        $oArmholeSeam      = $op('Armhole Seam');
        $oNecklineSeam     = $op('Neckline Seam');
        $oUnderwireCasing  = $op('Underwire Casing Seam');
        $oBridgeSeam       = $op('Bridge Seam');
        $oWingSeam         = $op('Wing Seam');
        $oLegHem           = $op('Leg Hem');
        $oWaistHem         = $op('Waist Hem');
        $oNeckHem          = $op('Neck Hem');
        $oCoverstitchLeg   = $op('Coverstitch Leg Hem');
        $oShoulderHem      = $op('Shoulder Hem');
        $oElasticWaist     = $op('Elastic Attach Waist');
        $oElasticLeg       = $op('Elastic Attach Leg');
        $oLaceTrim         = $op('Lace Trim Attach');
        $oUnderwireInsert  = $op('Underwire Insert');
        $oStrapAttach      = $op('Strap Attach');
        $oClaspAttach      = $op('Clasp Attach');
        $oLabelAttach      = $op('Label Attach');   // existing op 36
        $oHookEye          = $op('Hook & Eye Attach'); // existing op 33
        $oFinalPressing    = $op('Final Pressing');
        $oFinalInspection  = $op('Final Inspection');
        $oNeedleScan       = $op('Needle Detection Scan');
        $oSideSeam         = $op('Side Seam Join'); // existing op 4
        $oShoulderJoin     = $op('Shoulder Join');  // existing op 3

        // ── BRA (product_category_id = 2) ──
        $this->insertGradings(2, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.50, 'OG-BRA-001', 'Bra - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.20, 'OG-BRA-002', 'Bra - Cutting'],
            [3,  $oFrontPanelFusing,$FSM,  $GRB, 0.80, 'OG-BRA-003', 'Bra - Front Panel Fusing'],
            [4,  $oCupAssembly,     $SNLS, $GRD, 3.50, 'OG-BRA-004', 'Bra - Cup Assembly'],
            [5,  $oUnderwireCasing, $SNLS, $GRD, 2.80, 'OG-BRA-005', 'Bra - Underwire Casing Seam'],
            [6,  $oBridgeSeam,      $SNLS, $GRC, 1.50, 'OG-BRA-006', 'Bra - Bridge Seam'],
            [7,  $oWingSeam,        $SNLS, $GRC, 2.20, 'OG-BRA-007', 'Bra - Wing Seam'],
            [8,  $oBackSeam,        $OVL5, $GRC, 1.80, 'OG-BRA-008', 'Bra - Back Seam'],
            [9,  $oStrapAttach,     $SNLS, $GRC, 2.50, 'OG-BRA-009', 'Bra - Strap Attach'],
            [10, $oClaspAttach,     $SNLS, $GRD, 3.00, 'OG-BRA-010', 'Bra - Clasp Attach'],
            [11, $oHookEye,         $BTK,  $GRC, 1.50, 'OG-BRA-011', 'Bra - Hook and Eye Attach'],
            [12, $oElasticLeg,      $EAM,  $GRC, 1.80, 'OG-BRA-012', 'Bra - Elastic Leg Attach'],
            [13, $oLabelAttach,     $LAM,  $GRA, 0.40, 'OG-BRA-013', 'Bra - Label Attach'],
            [14, $oUnderwireInsert, $MNL,  $GRB, 1.80, 'OG-BRA-014', 'Bra - Underwire Insert'],
            [15, $oFinalPressing,   $STI,  $GRB, 1.20, 'OG-BRA-015', 'Bra - Final Pressing'],
            [16, $oFinalInspection, $MST,  $GRC, 1.50, 'OG-BRA-016', 'Bra - Final Inspection'],
            [17, $oNeedleScan,      $NDD,  $GRA, 0.30, 'OG-BRA-017', 'Bra - Needle Detection'],
        ]);

        // ── BRALETTE (product_category_id = 4) ──
        $this->insertGradings(4, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.45, 'OG-BRL-001', 'Bralette - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.90, 'OG-BRL-002', 'Bralette - Cutting'],
            [3,  $oCupAssembly,     $SNLS, $GRC, 2.50, 'OG-BRL-003', 'Bralette - Cup Assembly'],
            [4,  $oSideSeam,        $OVL5, $GRC, 1.50, 'OG-BRL-004', 'Bralette - Side Seam'],
            [5,  $oBackSeam,        $OVL5, $GRC, 1.20, 'OG-BRL-005', 'Bralette - Back Seam'],
            [6,  $oStrapAttach,     $SNLS, $GRC, 2.00, 'OG-BRL-006', 'Bralette - Strap Attach'],
            [7,  $oElasticLeg,      $EAM,  $GRC, 1.80, 'OG-BRL-007', 'Bralette - Elastic Leg Attach'],
            [8,  $oLaceTrim,        $SNLS, $GRD, 2.80, 'OG-BRL-008', 'Bralette - Lace Trim Attach'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.40, 'OG-BRL-009', 'Bralette - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 0.90, 'OG-BRL-010', 'Bralette - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.10, 'OG-BRL-011', 'Bralette - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-BRL-012', 'Bralette - Needle Detection'],
        ]);

        // ── SPORTS BRA (product_category_id = 5) ──
        $this->insertGradings(5, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.55, 'OG-SPB-001', 'Sports Bra - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.00, 'OG-SPB-002', 'Sports Bra - Cutting'],
            [3,  $oFBPanelJoin,     $FLK,  $GRC, 2.50, 'OG-SPB-003', 'Sports Bra - Panel Join Flatlock'],
            [4,  $oArmholeSeam,     $FLK,  $GRC, 2.20, 'OG-SPB-004', 'Sports Bra - Armhole Flatlock'],
            [5,  $oNecklineSeam,    $FLK,  $GRC, 1.80, 'OG-SPB-005', 'Sports Bra - Neckline Flatlock'],
            [6,  $oBandAttach,      $SNLS, $GRC, 2.50, 'OG-SPB-006', 'Sports Bra - Band Attach'],
            [7,  $oStrapAttach,     $SNLS, $GRC, 2.00, 'OG-SPB-007', 'Sports Bra - Strap Attach'],
            [8,  $oLegHem,          $FLK,  $GRC, 1.50, 'OG-SPB-008', 'Sports Bra - Bottom Hem Flatlock'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.40, 'OG-SPB-009', 'Sports Bra - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 1.00, 'OG-SPB-010', 'Sports Bra - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.20, 'OG-SPB-011', 'Sports Bra - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-SPB-012', 'Sports Bra - Needle Detection'],
        ]);

        // ── THONG (product_category_id = 8) ──
        $this->insertGradings(8, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.40, 'OG-THG-001', 'Thong - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.80, 'OG-THG-002', 'Thong - Cutting'],
            [3,  $oFrontRise,       $SNLS, $GRC, 1.20, 'OG-THG-003', 'Thong - Front Rise Seam'],
            [4,  $oCrotchSeam,      $OVL5, $GRC, 1.50, 'OG-THG-004', 'Thong - Crotch Seam'],
            [5,  $oBackSeam,        $SNLS, $GRC, 1.00, 'OG-THG-005', 'Thong - Back Seam'],
            [6,  $oElasticWaist,    $EAM,  $GRC, 2.00, 'OG-THG-006', 'Thong - Waist Elastic Attach'],
            [7,  $oElasticLeg,      $EAM,  $GRC, 2.50, 'OG-THG-007', 'Thong - Leg Elastic Attach'],
            [8,  $oLaceTrim,        $SNLS, $GRD, 3.00, 'OG-THG-008', 'Thong - Lace Trim Attach'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-THG-009', 'Thong - Label Attach'],
            [10, $oFinalInspection, $MST,  $GRC, 1.00, 'OG-THG-010', 'Thong - Final Inspection'],
            [11, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-THG-011', 'Thong - Needle Detection'],
        ]);

        // ── HIPSTER (product_category_id = 9) ──
        $this->insertGradings(9, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.40, 'OG-HIP-001', 'Hipster - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.85, 'OG-HIP-002', 'Hipster - Cutting'],
            [3,  $oFrontRise,       $SNLS, $GRC, 1.20, 'OG-HIP-003', 'Hipster - Front Rise Seam'],
            [4,  $oCrotchSeam,      $OVL5, $GRC, 1.50, 'OG-HIP-004', 'Hipster - Crotch Seam'],
            [5,  $oSideSeam,        $OVL3, $GRC, 1.40, 'OG-HIP-005', 'Hipster - Side Seam'],
            [6,  $oLegHem,          $SNLS, $GRC, 2.00, 'OG-HIP-006', 'Hipster - Leg Hem'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 1.80, 'OG-HIP-007', 'Hipster - Waist Elastic Attach'],
            [8,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-HIP-008', 'Hipster - Label Attach'],
            [9,  $oFinalInspection, $MST,  $GRC, 0.90, 'OG-HIP-009', 'Hipster - Final Inspection'],
            [10, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-HIP-010', 'Hipster - Needle Detection'],
        ]);

        // ── BRIEF (product_category_id = 10) ──
        $this->insertGradings(10, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.45, 'OG-BRF-001', 'Brief - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.90, 'OG-BRF-002', 'Brief - Cutting'],
            [3,  $oFrontRise,       $SNLS, $GRC, 1.30, 'OG-BRF-003', 'Brief - Front Rise Seam'],
            [4,  $oCrotchSeam,      $OVL5, $GRC, 1.60, 'OG-BRF-004', 'Brief - Crotch Seam'],
            [5,  $oBackSeam,        $OVL5, $GRC, 1.20, 'OG-BRF-005', 'Brief - Back Seam'],
            [6,  $oSideSeam,        $OVL3, $GRC, 1.50, 'OG-BRF-006', 'Brief - Side Seam'],
            [7,  $oLegHem,          $SNLS, $GRC, 2.20, 'OG-BRF-007', 'Brief - Leg Hem'],
            [8,  $oElasticWaist,    $EAM,  $GRC, 2.00, 'OG-BRF-008', 'Brief - Waist Elastic Attach'],
            [9,  $oElasticLeg,      $EAM,  $GRC, 2.80, 'OG-BRF-009', 'Brief - Leg Elastic Attach'],
            [10, $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-BRF-010', 'Brief - Label Attach'],
            [11, $oFinalPressing,   $STI,  $GRB, 0.80, 'OG-BRF-011', 'Brief - Final Pressing'],
            [12, $oFinalInspection, $MST,  $GRC, 1.00, 'OG-BRF-012', 'Brief - Final Inspection'],
            [13, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-BRF-013', 'Brief - Needle Detection'],
        ]);

        // ── BOYSHORT (product_category_id = 11) ──
        $this->insertGradings(11, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.45, 'OG-BYS-001', 'Boyshort - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.85, 'OG-BYS-002', 'Boyshort - Cutting'],
            [3,  $oInseam,          $OVL5, $GRC, 1.50, 'OG-BYS-003', 'Boyshort - Inseam Seam'],
            [4,  $oCrotchSeam,      $OVL5, $GRC, 1.50, 'OG-BYS-004', 'Boyshort - Crotch Seam'],
            [5,  $oOutseam,         $OVL5, $GRC, 1.50, 'OG-BYS-005', 'Boyshort - Outseam Seam'],
            [6,  $oCoverstitchLeg,  $FLK,  $GRC, 2.20, 'OG-BYS-006', 'Boyshort - Leg Hem Flatlock'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 1.80, 'OG-BYS-007', 'Boyshort - Waist Elastic Attach'],
            [8,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-BYS-008', 'Boyshort - Label Attach'],
            [9,  $oFinalPressing,   $STI,  $GRB, 0.80, 'OG-BYS-009', 'Boyshort - Final Pressing'],
            [10, $oFinalInspection, $MST,  $GRC, 0.90, 'OG-BYS-010', 'Boyshort - Final Inspection'],
            [11, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-BYS-011', 'Boyshort - Needle Detection'],
        ]);

        // ── T-SHIRT (product_category_id = 24) ──
        $this->insertGradings(24, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.55, 'OG-TSH-001', 'T-Shirt - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.00, 'OG-TSH-002', 'T-Shirt - Cutting'],
            [3,  $oShoulderJoin,    $OVL5, $GRC, 1.50, 'OG-TSH-003', 'T-Shirt - Shoulder Seam'],
            [4,  $oArmholeSeam,     $OVL5, $GRC, 2.00, 'OG-TSH-004', 'T-Shirt - Armhole Seam'],
            [5,  $oSideSeam,        $OVL5, $GRC, 1.80, 'OG-TSH-005', 'T-Shirt - Side Seam'],
            [6,  $oNeckHem,         $SNLS, $GRC, 2.20, 'OG-TSH-006', 'T-Shirt - Neck Hem'],
            [7,  $oShoulderHem,     $FLK,  $GRC, 1.80, 'OG-TSH-007', 'T-Shirt - Sleeve Hem'],
            [8,  $oWaistHem,        $FLK,  $GRC, 1.50, 'OG-TSH-008', 'T-Shirt - Bottom Hem'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.40, 'OG-TSH-009', 'T-Shirt - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 1.00, 'OG-TSH-010', 'T-Shirt - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.20, 'OG-TSH-011', 'T-Shirt - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-TSH-012', 'T-Shirt - Needle Detection'],
        ]);

        // ── SHORT (product_category_id = 1) ──
        $this->insertGradings(1, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.50, 'OG-SHT-001', 'Short - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.90, 'OG-SHT-002', 'Short - Cutting'],
            [3,  $oInseam,          $OVL5, $GRC, 1.80, 'OG-SHT-003', 'Short - Inseam Seam'],
            [4,  $oOutseam,         $OVL5, $GRC, 1.80, 'OG-SHT-004', 'Short - Outseam Seam'],
            [5,  $oCrotchSeam,      $OVL5, $GRC, 1.50, 'OG-SHT-005', 'Short - Crotch Seam'],
            [6,  $oWaistHem,        $SNLS, $GRC, 1.80, 'OG-SHT-006', 'Short - Waist Hem'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 2.00, 'OG-SHT-007', 'Short - Waist Elastic Attach'],
            [8,  $oCoverstitchLeg,  $FLK,  $GRC, 2.50, 'OG-SHT-008', 'Short - Leg Hem Flatlock'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-SHT-009', 'Short - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 0.80, 'OG-SHT-010', 'Short - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.00, 'OG-SHT-011', 'Short - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-SHT-012', 'Short - Needle Detection'],
        ]);

        // ── TANK (product_category_id = 26) ──
        $this->insertGradings(26, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.50, 'OG-TNK-001', 'Tank - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 0.90, 'OG-TNK-002', 'Tank - Cutting'],
            [3,  $oShoulderJoin,    $OVL5, $GRC, 1.40, 'OG-TNK-003', 'Tank - Shoulder Seam'],
            [4,  $oSideSeam,        $OVL5, $GRC, 1.80, 'OG-TNK-004', 'Tank - Side Seam'],
            [5,  $oArmholeSeam,     $SNLS, $GRC, 2.00, 'OG-TNK-005', 'Tank - Armhole Hem'],
            [6,  $oNeckHem,         $SNLS, $GRC, 2.00, 'OG-TNK-006', 'Tank - Neck Hem'],
            [7,  $oWaistHem,        $SNLS, $GRC, 1.50, 'OG-TNK-007', 'Tank - Bottom Hem'],
            [8,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-TNK-008', 'Tank - Label Attach'],
            [9,  $oFinalPressing,   $STI,  $GRB, 0.80, 'OG-TNK-009', 'Tank - Final Pressing'],
            [10, $oFinalInspection, $MST,  $GRC, 1.00, 'OG-TNK-010', 'Tank - Final Inspection'],
            [11, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-TNK-011', 'Tank - Needle Detection'],
        ]);

        // ── LEGGING (product_category_id = 41) ──
        $this->insertGradings(41, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.60, 'OG-LEG-001', 'Legging - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.10, 'OG-LEG-002', 'Legging - Cutting'],
            [3,  $oInseam,          $FLK,  $GRC, 2.50, 'OG-LEG-003', 'Legging - Inseam Flatlock'],
            [4,  $oOutseam,         $FLK,  $GRC, 2.50, 'OG-LEG-004', 'Legging - Outseam Flatlock'],
            [5,  $oCrotchSeam,      $OVL5, $GRC, 1.80, 'OG-LEG-005', 'Legging - Crotch Seam'],
            [6,  $oWaistHem,        $SNLS, $GRC, 2.00, 'OG-LEG-006', 'Legging - Waist Hem'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 2.20, 'OG-LEG-007', 'Legging - Waist Elastic Attach'],
            [8,  $oCoverstitchLeg,  $FLK,  $GRC, 2.80, 'OG-LEG-008', 'Legging - Leg Hem Flatlock'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-LEG-009', 'Legging - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 1.00, 'OG-LEG-010', 'Legging - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.20, 'OG-LEG-011', 'Legging - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-LEG-012', 'Legging - Needle Detection'],
        ]);

        // ── JOGGER (product_category_id = 42) ──
        $this->insertGradings(42, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.60, 'OG-JOG-001', 'Jogger - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.10, 'OG-JOG-002', 'Jogger - Cutting'],
            [3,  $oInseam,          $OVL5, $GRC, 2.00, 'OG-JOG-003', 'Jogger - Inseam Seam'],
            [4,  $oOutseam,         $OVL5, $GRC, 2.00, 'OG-JOG-004', 'Jogger - Outseam Seam'],
            [5,  $oCrotchSeam,      $OVL5, $GRC, 1.80, 'OG-JOG-005', 'Jogger - Crotch Seam'],
            [6,  $oWaistHem,        $SNLS, $GRC, 2.00, 'OG-JOG-006', 'Jogger - Waist Hem'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 2.50, 'OG-JOG-007', 'Jogger - Waist Elastic Attach'],
            [8,  $oLegHem,          $SNLS, $GRC, 2.50, 'OG-JOG-008', 'Jogger - Leg Hem'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.35, 'OG-JOG-009', 'Jogger - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 1.00, 'OG-JOG-010', 'Jogger - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.20, 'OG-JOG-011', 'Jogger - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-JOG-012', 'Jogger - Needle Detection'],
        ]);

        // ── SWIMWEAR (product_category_id = 52) ──
        $this->insertGradings(52, [
            [1,  $oFabricLaying,    $MST,  $GRA, 0.55, 'OG-SWM-001', 'Swimwear - Fabric Laying'],
            [2,  $oStraightCut,     $SKC,  $GRB, 1.00, 'OG-SWM-002', 'Swimwear - Cutting'],
            [3,  $oFrontPanelFusing,$FSM,  $GRB, 0.70, 'OG-SWM-003', 'Swimwear - Front Panel Fusing'],
            [4,  $oLinerAttach,     $SNLS, $GRC, 2.50, 'OG-SWM-004', 'Swimwear - Liner Attach'],
            [5,  $oCrotchSeam,      $OVL5, $GRC, 1.80, 'OG-SWM-005', 'Swimwear - Crotch Seam'],
            [6,  $oBackSeam,        $OVL5, $GRC, 1.50, 'OG-SWM-006', 'Swimwear - Back Seam'],
            [7,  $oElasticWaist,    $EAM,  $GRC, 2.00, 'OG-SWM-007', 'Swimwear - Waist Elastic Attach'],
            [8,  $oElasticLeg,      $EAM,  $GRC, 2.50, 'OG-SWM-008', 'Swimwear - Leg Elastic Attach'],
            [9,  $oLabelAttach,     $LAM,  $GRA, 0.40, 'OG-SWM-009', 'Swimwear - Label Attach'],
            [10, $oFinalPressing,   $STI,  $GRB, 1.00, 'OG-SWM-010', 'Swimwear - Final Pressing'],
            [11, $oFinalInspection, $MST,  $GRC, 1.30, 'OG-SWM-011', 'Swimwear - Final Inspection'],
            [12, $oNeedleScan,      $NDD,  $GRA, 0.25, 'OG-SWM-012', 'Swimwear - Needle Detection'],
        ]);
    }

    /**
     * Insert operation rows (table `operations`, was `operation_gradings`) for a given product_category_id.
     * Skips rows whose code or (op, product_cat, machine_type) unique key already exists.
     *
     * @param  int    $productCategoryId
     * @param  array  $rows  Each: [sequence_no, base_operation_id, machine_type_id, grade_id, smv, code, description]
     */
    private function insertGradings(int $productCategoryId, array $rows): void
    {
        foreach ($rows as [$seq, $opId, $mtId, $gradeId, $smv, $code, $desc]) {
            // Skip if any unique key already present
            $exists = DB::table('operations')
                ->where('code', $code)
                ->orWhere(function ($q) use ($opId, $productCategoryId, $mtId) {
                    $q->where('base_operation_id', $opId)
                      ->where('product_category_id', $productCategoryId)
                      ->where('machine_type_id', $mtId);
                })
                ->exists();

            if ($exists) {
                continue;
            }

            // Check if this sequence_no is already taken for this product_category
            $seqTaken = DB::table('operations')
                ->where('product_category_id', $productCategoryId)
                ->where('sequence_no', $seq)
                ->exists();

            // Find next available sequence if taken
            $seqNo = $seqTaken
                ? (DB::table('operations')
                    ->where('product_category_id', $productCategoryId)
                    ->max('sequence_no') + 1)
                : $seq;

            DB::table('operations')->insert([
                'base_operation_id'  => $opId,
                'product_category_id'=> $productCategoryId,
                'machine_type_id'    => $mtId,
                'description'        => $desc,
                'code'               => $code,
                'grade_id'           => $gradeId,
                'sequence_no'        => $seqNo,
                'smv'                => $smv,
                'is_active'          => 1,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 7. PRODUCTS (62 products across 14 categories)
    // ─────────────────────────────────────────────────────────────
    private function seedProducts(): void
    {
        DB::table('products')->insertOrIgnore([
            // BRA (cat 2)
            ['description' => 'Ladies Underwire Bra Classic',    'code' => 'P-BRA-001', 'product_category_id' => 2,  'is_active' => 1],
            ['description' => 'Ladies Push-Up Bra Deluxe',       'code' => 'P-BRA-002', 'product_category_id' => 2,  'is_active' => 1],
            ['description' => 'Seamless T-Shirt Bra',            'code' => 'P-BRA-003', 'product_category_id' => 2,  'is_active' => 1],
            ['description' => 'Strapless Multi-Way Bra',         'code' => 'P-BRA-004', 'product_category_id' => 2,  'is_active' => 1],
            ['description' => 'Nursing Bra Comfort Plus',        'code' => 'P-BRA-005', 'product_category_id' => 2,  'is_active' => 1],
            ['description' => 'Minimizer Bra Full Coverage',     'code' => 'P-BRA-006', 'product_category_id' => 2,  'is_active' => 1],
            // BRALETTE (cat 4)
            ['description' => 'Wire-Free Bralette Essential',    'code' => 'P-BRL-001', 'product_category_id' => 4,  'is_active' => 1],
            ['description' => 'Lace Bralette Trim Style',        'code' => 'P-BRL-002', 'product_category_id' => 4,  'is_active' => 1],
            ['description' => 'Crop Bralette Active',            'code' => 'P-BRL-003', 'product_category_id' => 4,  'is_active' => 1],
            // SPORTS BRA (cat 5)
            ['description' => 'High Impact Sports Bra Pro',      'code' => 'P-SPB-001', 'product_category_id' => 5,  'is_active' => 1],
            ['description' => 'Medium Support Sports Bra',       'code' => 'P-SPB-002', 'product_category_id' => 5,  'is_active' => 1],
            ['description' => 'Low Impact Yoga Bra',             'code' => 'P-SPB-003', 'product_category_id' => 5,  'is_active' => 1],
            ['description' => 'Racerback Training Bra',          'code' => 'P-SPB-004', 'product_category_id' => 5,  'is_active' => 1],
            // THONG (cat 8)
            ['description' => 'Classic Cotton Thong',            'code' => 'P-THG-001', 'product_category_id' => 8,  'is_active' => 1],
            ['description' => 'Lace Trim Thong',                 'code' => 'P-THG-002', 'product_category_id' => 8,  'is_active' => 1],
            ['description' => 'High Waist Thong Shaper',         'code' => 'P-THG-003', 'product_category_id' => 8,  'is_active' => 1],
            // HIPSTER (cat 9)
            ['description' => 'Cotton Hipster Brief',            'code' => 'P-HIP-001', 'product_category_id' => 9,  'is_active' => 1],
            ['description' => 'Seamless Hipster Style',          'code' => 'P-HIP-002', 'product_category_id' => 9,  'is_active' => 1],
            ['description' => 'Microfiber Hipster',              'code' => 'P-HIP-003', 'product_category_id' => 9,  'is_active' => 1],
            // BRIEF (cat 10)
            ['description' => 'Classic Full Brief',              'code' => 'P-BRF-001', 'product_category_id' => 10, 'is_active' => 1],
            ['description' => 'High Waist Brief Control',        'code' => 'P-BRF-002', 'product_category_id' => 10, 'is_active' => 1],
            ['description' => 'Cotton Brief Comfort',            'code' => 'P-BRF-003', 'product_category_id' => 10, 'is_active' => 1],
            ['description' => 'Lace Trim Brief',                 'code' => 'P-BRF-004', 'product_category_id' => 10, 'is_active' => 1],
            // BOYSHORT (cat 11)
            ['description' => 'Cotton Boyshort Classic',         'code' => 'P-BYS-001', 'product_category_id' => 11, 'is_active' => 1],
            ['description' => 'Lace Boyshort Trim',              'code' => 'P-BYS-002', 'product_category_id' => 11, 'is_active' => 1],
            ['description' => 'Modal Boyshort Comfort',          'code' => 'P-BYS-003', 'product_category_id' => 11, 'is_active' => 1],
            // BOXER (cat 12)
            ['description' => 'Mens Cotton Boxer Standard',      'code' => 'P-BOX-001', 'product_category_id' => 12, 'is_active' => 1],
            ['description' => 'Mens Trunk Boxer Style',          'code' => 'P-BOX-002', 'product_category_id' => 12, 'is_active' => 1],
            // BIKINI (cat 14)
            ['description' => 'Classic Bikini Bottom',           'code' => 'P-BKN-001', 'product_category_id' => 14, 'is_active' => 1],
            ['description' => 'High Waist Bikini Bottom',        'code' => 'P-BKN-002', 'product_category_id' => 14, 'is_active' => 1],
            ['description' => 'Tie-Side Bikini Bottom',          'code' => 'P-BKN-003', 'product_category_id' => 14, 'is_active' => 1],
            // BODY SUIT (cat 16)
            ['description' => 'Bodysuit Sleeveless Classic',     'code' => 'P-BDS-001', 'product_category_id' => 16, 'is_active' => 1],
            ['description' => 'Lace Bodysuit Long Sleeve',       'code' => 'P-BDS-002', 'product_category_id' => 16, 'is_active' => 1],
            // T-SHIRT (cat 24)
            ['description' => 'Womens Basic Crew Tee',           'code' => 'P-TSH-001', 'product_category_id' => 24, 'is_active' => 1],
            ['description' => 'Mens Classic T-Shirt',            'code' => 'P-TSH-002', 'product_category_id' => 24, 'is_active' => 1],
            ['description' => 'V-Neck T-Shirt Slim Fit',         'code' => 'P-TSH-003', 'product_category_id' => 24, 'is_active' => 1],
            ['description' => 'Oversized Graphic T-Shirt',       'code' => 'P-TSH-004', 'product_category_id' => 24, 'is_active' => 1],
            ['description' => 'Longline T-Shirt',                'code' => 'P-TSH-005', 'product_category_id' => 24, 'is_active' => 1],
            // TOP (cat 25)
            ['description' => 'Woven Blouse Top Style A',        'code' => 'P-TOP-001', 'product_category_id' => 25, 'is_active' => 1],
            ['description' => 'Jersey Knit Top Style B',         'code' => 'P-TOP-002', 'product_category_id' => 25, 'is_active' => 1],
            ['description' => 'Off-Shoulder Top Style C',        'code' => 'P-TOP-003', 'product_category_id' => 25, 'is_active' => 1],
            // TANK (cat 26)
            ['description' => 'Basic Tank Top Classic',          'code' => 'P-TNK-001', 'product_category_id' => 26, 'is_active' => 1],
            ['description' => 'Ribbed Tank Style',               'code' => 'P-TNK-002', 'product_category_id' => 26, 'is_active' => 1],
            ['description' => 'Longline Tank Active',            'code' => 'P-TNK-003', 'product_category_id' => 26, 'is_active' => 1],
            // CAMI (cat 28)
            ['description' => 'Satin Cami Deluxe',               'code' => 'P-CAM-001', 'product_category_id' => 28, 'is_active' => 1],
            ['description' => 'Lace Trim Cami',                  'code' => 'P-CAM-002', 'product_category_id' => 28, 'is_active' => 1],
            // HOODIE (cat 32)
            ['description' => 'Pullover Hoodie Fleece',          'code' => 'P-HOD-001', 'product_category_id' => 32, 'is_active' => 1],
            ['description' => 'Zip-Up Hoodie Style',             'code' => 'P-HOD-002', 'product_category_id' => 32, 'is_active' => 1],
            // LEGGING (cat 41)
            ['description' => 'Full Length Legging Classic',     'code' => 'P-LEG-001', 'product_category_id' => 41, 'is_active' => 1],
            ['description' => 'Cropped Legging Active',          'code' => 'P-LEG-002', 'product_category_id' => 41, 'is_active' => 1],
            ['description' => 'High Waist Legging Pro',          'code' => 'P-LEG-003', 'product_category_id' => 41, 'is_active' => 1],
            ['description' => 'Compression Legging',             'code' => 'P-LEG-004', 'product_category_id' => 41, 'is_active' => 1],
            ['description' => 'Seamless Legging Minimal',        'code' => 'P-LEG-005', 'product_category_id' => 41, 'is_active' => 1],
            // JOGGER (cat 42)
            ['description' => 'Fleece Jogger Classic',           'code' => 'P-JOG-001', 'product_category_id' => 42, 'is_active' => 1],
            ['description' => 'Slim Fit Jogger',                 'code' => 'P-JOG-002', 'product_category_id' => 42, 'is_active' => 1],
            ['description' => 'Cotton Jogger Comfort',           'code' => 'P-JOG-003', 'product_category_id' => 42, 'is_active' => 1],
            // SHORT (cat 1)
            ['description' => 'Athletic Training Short',         'code' => 'P-SHT-001', 'product_category_id' => 1,  'is_active' => 1],
            ['description' => 'Casual Woven Short',              'code' => 'P-SHT-002', 'product_category_id' => 1,  'is_active' => 1],
            ['description' => 'Compression Short',               'code' => 'P-SHT-003', 'product_category_id' => 1,  'is_active' => 1],
            ['description' => 'Running Short',                   'code' => 'P-SHT-004', 'product_category_id' => 1,  'is_active' => 1],
            // SWIMWEAR (cat 52)
            ['description' => 'One Piece Swimsuit Classic',      'code' => 'P-SWM-001', 'product_category_id' => 52, 'is_active' => 1],
            ['description' => 'Bikini Set Full Style',           'code' => 'P-SWM-002', 'product_category_id' => 52, 'is_active' => 1],
            ['description' => 'Rash Guard Top',                  'code' => 'P-SWM-003', 'product_category_id' => 52, 'is_active' => 1],
            // DRESS (cat 39)
            ['description' => 'Maxi Dress Summer Style',        'code' => 'P-DRS-001', 'product_category_id' => 39, 'is_active' => 1],
            ['description' => 'Mini Dress Casual Style',        'code' => 'P-DRS-002', 'product_category_id' => 39, 'is_active' => 1],
            // SKIRT (cat 40)
            ['description' => 'A-Line Midi Skirt',              'code' => 'P-SKT-001', 'product_category_id' => 40, 'is_active' => 1],
            ['description' => 'Mini Skirt Flare Style',         'code' => 'P-SKT-002', 'product_category_id' => 40, 'is_active' => 1],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 8. PRODUCT OPERATIONS (was PRODUCT OPERATION GRADINGS)
    // ─────────────────────────────────────────────────────────────
    private function seedProductOperationGradings(): void
    {
        // Map: product_code → [og_code_prefix, smv_multiplier]
        $productToCategory = [
            // BRA products → BRA OGs (17 ops)
            'P-BRA-001' => ['OG-BRA-', 1.00],
            'P-BRA-002' => ['OG-BRA-', 1.08],
            'P-BRA-003' => ['OG-BRA-', 0.92],
            'P-BRA-004' => ['OG-BRA-', 1.03],
            'P-BRA-005' => ['OG-BRA-', 1.05],
            'P-BRA-006' => ['OG-BRA-', 1.02],
            // BRALETTE products → BRL OGs (12 ops)
            'P-BRL-001' => ['OG-BRL-', 1.00],
            'P-BRL-002' => ['OG-BRL-', 1.10],
            'P-BRL-003' => ['OG-BRL-', 0.93],
            // SPORTS BRA → SPB OGs (12 ops)
            'P-SPB-001' => ['OG-SPB-', 1.00],
            'P-SPB-002' => ['OG-SPB-', 0.92],
            'P-SPB-003' => ['OG-SPB-', 0.85],
            'P-SPB-004' => ['OG-SPB-', 0.96],
            // THONG → THG OGs (11 ops)
            'P-THG-001' => ['OG-THG-', 1.00],
            'P-THG-002' => ['OG-THG-', 1.10],
            'P-THG-003' => ['OG-THG-', 1.05],
            // HIPSTER → HIP OGs (10 ops)
            'P-HIP-001' => ['OG-HIP-', 1.00],
            'P-HIP-002' => ['OG-HIP-', 0.95],
            'P-HIP-003' => ['OG-HIP-', 1.00],
            // BRIEF → BRF OGs (13 ops)
            'P-BRF-001' => ['OG-BRF-', 1.00],
            'P-BRF-002' => ['OG-BRF-', 1.07],
            'P-BRF-003' => ['OG-BRF-', 0.94],
            'P-BRF-004' => ['OG-BRF-', 1.03],
            // BOYSHORT → BYS OGs (11 ops)
            'P-BYS-001' => ['OG-BYS-', 1.00],
            'P-BYS-002' => ['OG-BYS-', 1.09],
            'P-BYS-003' => ['OG-BYS-', 0.96],
            // T-SHIRT → TSH OGs (12 ops)
            'P-TSH-001' => ['OG-TSH-', 1.00],
            'P-TSH-002' => ['OG-TSH-', 1.05],
            'P-TSH-003' => ['OG-TSH-', 1.00],
            'P-TSH-004' => ['OG-TSH-', 1.08],
            'P-TSH-005' => ['OG-TSH-', 1.06],
            // TANK → TNK OGs (11 ops)
            'P-TNK-001' => ['OG-TNK-', 1.00],
            'P-TNK-002' => ['OG-TNK-', 1.00],
            'P-TNK-003' => ['OG-TNK-', 1.07],
            // LEGGING → LEG OGs (12 ops)
            'P-LEG-001' => ['OG-LEG-', 1.00],
            'P-LEG-002' => ['OG-LEG-', 0.90],
            'P-LEG-003' => ['OG-LEG-', 1.05],
            'P-LEG-004' => ['OG-LEG-', 1.10],
            'P-LEG-005' => ['OG-LEG-', 0.95],
            // JOGGER → JOG OGs (12 ops)
            'P-JOG-001' => ['OG-JOG-', 1.00],
            'P-JOG-002' => ['OG-JOG-', 1.00],
            'P-JOG-003' => ['OG-JOG-', 0.97],
            // SHORT → SHT OGs (12 ops)
            'P-SHT-001' => ['OG-SHT-', 1.00],
            'P-SHT-002' => ['OG-SHT-', 1.05],
            'P-SHT-003' => ['OG-SHT-', 1.00],
            'P-SHT-004' => ['OG-SHT-', 0.95],
            // SWIMWEAR → SWM OGs (12 ops)
            'P-SWM-001' => ['OG-SWM-', 1.00],
            'P-SWM-002' => ['OG-SWM-', 0.95],
            'P-SWM-003' => ['OG-SWM-', 0.88],
        ];

        foreach ($productToCategory as $productCode => [$ogPrefix, $smvMult]) {
            $productId = DB::table('products')->where('code', $productCode)->value('id');
            if (!$productId) {
                continue;
            }

            // Get all operation gradings whose code starts with the prefix, sorted by sequence
            $gradings = DB::table('operations')
                ->where('code', 'LIKE', $ogPrefix . '%')
                ->orderBy('sequence_no')
                ->get(['id', 'smv', 'sequence_no']);

            $seqNo = 1;
            foreach ($gradings as $grading) {
                $alreadyLinked = DB::table('product_operations')
                    ->where('product_id', $productId)
                    ->where('operation_id', $grading->id)
                    ->exists();

                if ($alreadyLinked) {
                    $seqNo++;
                    continue;
                }

                // Check if this sequence_no is already used for this product
                $seqTaken = DB::table('product_operations')
                    ->where('product_id', $productId)
                    ->where('sequence_no', $seqNo)
                    ->exists();

                $finalSeq = $seqTaken
                    ? (DB::table('product_operations')
                        ->where('product_id', $productId)
                        ->max('sequence_no') + 1)
                    : $seqNo;

                DB::table('product_operations')->insert([
                    'product_id'           => $productId,
                    'operation_id'         => $grading->id,
                    'sequence_no'          => $finalSeq,
                    'smv'                  => $grading->smv !== null
                        ? round($grading->smv * $smvMult, 4)
                        : null,
                    'is_active'            => 1,
                ]);

                $seqNo++;
            }
        }
    }
}
