<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// product_categories id=38 (name "TEDDY") had code "DRESS" duplicated with id=39
// (name "DRESS") - a copy-paste error. Fixed to "TEDDY" before adding the unique
// constraint, per confirmation that id=38's code should match its own name.
return new class extends Migration
{
    public function up()
    {
        DB::table('product_categories')->where('id', 38)->where('code', 'DRESS')->update(['code' => 'TEDDY']);

        DB::statement('ALTER TABLE product_categories MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('product_categories', function (Blueprint $table) {
            $table->unique('code', 'uniq_product_categories_code');
        });
    }

    public function down()
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('uniq_product_categories_code');
        });

        DB::statement('ALTER TABLE product_categories MODIFY code VARCHAR(50) NULL');
    }
};
