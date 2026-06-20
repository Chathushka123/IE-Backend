<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operation_gradings', function (Blueprint $table) {
            $table->unsignedInteger('sequence_no')->nullable()->after('grade_id');
            $table->decimal('smv', 8, 4)->nullable()->after('sequence_no');

            $table->unique(['product_category_id', 'sequence_no'], 'uniq_operation_gradings_product_category_sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_gradings', function (Blueprint $table) {
            $table->dropUnique('uniq_operation_gradings_product_category_sequence');
            $table->dropColumn(['sequence_no', 'smv']);
        });
    }
};
