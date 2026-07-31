<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Destinations were originally given a single nullable customer_id FK, but a
// destination can serve many customers and a customer can ship to many
// destinations - both optional. Replace the FK column with a pivot table,
// same shape as factory_product.
return new class extends Migration
{
    public function up()
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropForeign('fk_destinations_customer');
            $table->dropIndex('idx_destinations_customer');
            $table->dropColumn('customer_id');
        });

        Schema::create('customer_destination', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('destination_id');
            $table->timestamps();

            $table->unique(['customer_id', 'destination_id'], 'uniq_customer_destination');
            $table->foreign('customer_id', 'fk_customer_destination_customer')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('destination_id', 'fk_customer_destination_destination')->references('id')->on('destinations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_destination');

        Schema::table('destinations', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('code');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->foreign('customer_id', 'fk_destinations_customer')->references('id')->on('customers');
            $table->index('customer_id', 'idx_destinations_customer');
        });
    }
};
