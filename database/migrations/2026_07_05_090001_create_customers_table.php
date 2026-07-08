<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('description', 'uniq_customers_description');
            $table->unique('code', 'uniq_customers_code');
            $table->foreign('created_by_id', 'fk_customers_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_customers_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
