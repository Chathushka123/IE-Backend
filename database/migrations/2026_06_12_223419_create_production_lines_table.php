<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductionLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('department_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('category_id', 'fk_production_lines_category')->references('id')->on('line_categories');
            $table->foreign('department_id', 'fk_production_lines_department')->references('id')->on('departments');
            $table->foreign('created_by_id', 'fk_production_lines_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_production_lines_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('production_lines');
    }
}
