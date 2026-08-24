<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeFieldChangesTable extends Migration
{
    public function up()
    {
        Schema::create('employee_field_changes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->string('field', 50);

            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->string('old_label', 255)->nullable();
            $table->string('new_label', 255)->nullable();

            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name', 255)->nullable();

            $table->dateTime('created_at')->useCurrent();

            $table->foreign('employee_id', 'fk_employee_field_changes_employee')
                ->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('changed_by_user_id', 'fk_employee_field_changes_user')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['employee_id', 'created_at'], 'idx_employee_field_changes_employee_created');
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_field_changes');
    }
}
