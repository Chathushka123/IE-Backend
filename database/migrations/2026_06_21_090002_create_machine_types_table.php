<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMachineTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('machine_types', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('machine_category_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('description', 'uniq_machine_types_description');
            $table->unique('code', 'uniq_machine_types_code');
            $table->foreign('machine_category_id', 'fk_machine_types_machine_category')->references('id')->on('machine_categories');
            $table->foreign('created_by_id', 'fk_machine_types_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_machine_types_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('machine_types');
    }
}
