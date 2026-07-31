<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `products` CHANGE `description` `name` VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE `products` RENAME INDEX `uniq_products_description` TO `uniq_products_name`");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `products` RENAME INDEX `uniq_products_name` TO `uniq_products_description`");
        DB::statement("ALTER TABLE `products` CHANGE `name` `description` VARCHAR(255) NOT NULL");
    }
};
