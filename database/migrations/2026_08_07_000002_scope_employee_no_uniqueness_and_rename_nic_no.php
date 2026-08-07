<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::renameColumn() is unavailable — raw SQL
// is used, mirroring 2026_07_19_000003_rename_production_line_and_line_category_tables.
//
// 1. employees.employee_no was globally unique; the same employee_no can legitimately be
//    reused across different factories, so uniqueness is now scoped to (employee_no, factory_id).
// 2. employees.nic_no -> employees.identification_no — the column now also holds Passport/
//    Driving Licence numbers, not just NIC; only the DB column/JSON key renames here, the
//    "NIC No / Passport No / Driving Licence No" label is a frontend/export display change
//    (see EmployeesExport.php).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `employees` DROP INDEX `uk_employee_no`');
        DB::statement('ALTER TABLE `employees` ADD UNIQUE `uk_employee_no_factory` (`employee_no`, `factory_id`)');

        DB::statement('ALTER TABLE `employees` CHANGE `nic_no` `identification_no` VARCHAR(20) NOT NULL');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `uk_nic_no` TO `uk_identification_no`');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `idx_employees_nic` TO `idx_employees_identification`');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `employees` RENAME INDEX `idx_employees_identification` TO `idx_employees_nic`');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `uk_identification_no` TO `uk_nic_no`');
        DB::statement('ALTER TABLE `employees` CHANGE `identification_no` `nic_no` VARCHAR(20) NOT NULL');

        DB::statement('ALTER TABLE `employees` DROP INDEX `uk_employee_no_factory`');
        DB::statement('ALTER TABLE `employees` ADD UNIQUE `uk_employee_no` (`employee_no`)');
    }
};
