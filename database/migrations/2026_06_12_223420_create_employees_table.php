<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('employee_no', 50);
            $table->string('nic_no', 20);

            $table->string('full_name')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->date('birthday')->nullable();

            $table->string('email_address')->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('address', 500)->nullable();

            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Other'])->nullable();

            $table->string('photo_url', 500)->nullable();

            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();

            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->date('confirmation_date')->nullable();

            $table->enum('employment_type', ['Permanent', 'Contract', 'Casual'])->nullable();

            $table->unsignedBigInteger('reporting_manager_id')->nullable();
            $table->unsignedBigInteger('production_line_id')->nullable();
            $table->unsignedBigInteger('base_line_id')->nullable();

            $table->enum('employee_status', ['Active', 'Resigned', 'Terminated'])->default('Active');

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();

            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Unique keys
            $table->unique('employee_no', 'uk_employee_no');
            $table->unique('nic_no', 'uk_nic_no');

            // Foreign keys
            $table->foreign('category_id', 'fk_employees_category')->references('id')->on('employee_categories');
            $table->foreign('department_id', 'fk_employees_department')->references('id')->on('departments');
            $table->foreign('designation_id', 'fk_employees_designation')->references('id')->on('designations');
            $table->foreign('reporting_manager_id', 'fk_employees_reporting_manager')->references('id')->on('employees');
            $table->foreign('production_line_id', 'fk_employees_production_line')->references('id')->on('production_lines');
            $table->foreign('base_line_id', 'fk_employees_base_line')->references('id')->on('production_lines');
            $table->foreign('created_by_id', 'fk_employees_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_employees_updated_by')->references('id')->on('users');

            // Indexes
            $table->index('department_id', 'idx_employees_department');
            $table->index('designation_id', 'idx_employees_designation');
            $table->index('production_line_id', 'idx_employees_line');
            $table->index('reporting_manager_id', 'idx_employees_manager');
            $table->index('nic_no', 'idx_employees_nic');
            $table->index('employee_no', 'idx_employees_empno');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
}
