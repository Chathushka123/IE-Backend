<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('name', 'uniq_destinations_name');
            $table->unique('code', 'uniq_destinations_code');
            $table->foreign('customer_id', 'fk_destinations_customer')->references('id')->on('customers');
            $table->foreign('created_by_id', 'fk_destinations_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_destinations_updated_by')->references('id')->on('users');
            $table->index('customer_id', 'idx_destinations_customer');
        });
    }

    public function down()
    {
        Schema::dropIfExists('destinations');
    }
};
