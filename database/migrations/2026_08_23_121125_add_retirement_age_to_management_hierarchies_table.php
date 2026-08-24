<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('management_hierarchies', function (Blueprint $table) {
            $table->unsignedTinyInteger('retirement_age')->default(55)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('management_hierarchies', function (Blueprint $table) {
            $table->dropColumn('retirement_age');
        });
    }
};
