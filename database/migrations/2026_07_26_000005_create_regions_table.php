<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['country_id', 'name'], 'uniq_regions_country_name');
            $table->unique('code', 'uniq_regions_code');
            $table->foreign('country_id', 'fk_regions_country')->references('id')->on('countries');
            $table->foreign('created_by_id', 'fk_regions_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_regions_updated_by')->references('id')->on('users');
            $table->index('country_id', 'idx_regions_country');
        });
    }

    public function down()
    {
        Schema::dropIfExists('regions');
    }
};
