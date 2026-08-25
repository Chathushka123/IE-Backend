<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_milestones', function (Blueprint $table) {
            $table->unsignedInteger('planned_quantity')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_milestones', function (Blueprint $table) {
            $table->dropColumn('planned_quantity');
        });
    }
};
