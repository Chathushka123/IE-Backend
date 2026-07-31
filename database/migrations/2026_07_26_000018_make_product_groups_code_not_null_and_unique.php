<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Verified no NULL or duplicate codes exist in product_groups at the time of this migration.
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE product_groups MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('product_groups', function (Blueprint $table) {
            $table->unique('code', 'uniq_product_groups_code');
        });
    }

    public function down()
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropUnique('uniq_product_groups_code');
        });

        DB::statement('ALTER TABLE product_groups MODIFY code VARCHAR(50) NULL');
    }
};
