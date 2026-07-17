<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('factory_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['factory_id', 'user_id'], 'uniq_factory_user');
            $table->foreign('factory_id', 'fk_factory_user_factory')->references('id')->on('factories')->onDelete('cascade');
            $table->foreign('user_id', 'fk_factory_user_user')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('factory_user');
    }
};
