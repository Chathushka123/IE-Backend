<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Pre-launch dev data only — no machine_type existed when these rows were created,
        // so there's nothing valid to backfill machine_type_id with.
        DB::table('operation_gradings')->truncate();

        Schema::table('operation_gradings', function (Blueprint $table) {
            // The composite unique below backs the operation_id FK, so it must go first.
            $table->dropForeign('fk_operation_gradings_operation');
            $table->dropUnique('uniq_operation_gradings_operation_product_category');

            $table->unsignedBigInteger('machine_type_id')->after('product_category_id');
            $table->string('description')->nullable()->after('machine_type_id');
            $table->string('code', 50)->nullable()->after('description');

            $table->unique(['operation_id', 'product_category_id', 'machine_type_id'], 'uniq_operation_gradings_operation_product_category_machine');
            $table->unique('code', 'uniq_operation_gradings_code');

            $table->foreign('operation_id', 'fk_operation_gradings_operation')->references('id')->on('operations');
            $table->foreign('machine_type_id', 'fk_operation_gradings_machine_type')->references('id')->on('machine_types');
        });
    }

    public function down()
    {
        Schema::table('operation_gradings', function (Blueprint $table) {
            $table->dropForeign('fk_operation_gradings_operation');
            $table->dropForeign('fk_operation_gradings_machine_type');

            $table->dropUnique('uniq_operation_gradings_code');
            $table->dropUnique('uniq_operation_gradings_operation_product_category_machine');

            $table->dropColumn(['machine_type_id', 'description', 'code']);

            $table->unique(['operation_id', 'product_category_id'], 'uniq_operation_gradings_operation_product_category');
            $table->foreign('operation_id', 'fk_operation_gradings_operation')->references('id')->on('operations');
        });
    }
};
