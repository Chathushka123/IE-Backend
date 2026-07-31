<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('code');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('code');
        });
    }

    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
