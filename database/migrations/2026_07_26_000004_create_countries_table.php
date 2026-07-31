<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('name', 'uniq_countries_name');
            $table->unique('code', 'uniq_countries_code');
            $table->foreign('created_by_id', 'fk_countries_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_countries_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
