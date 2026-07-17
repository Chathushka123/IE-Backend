<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nullable by design: null means the permission applies to every factory,
// a value overrides the role's access for that one factory only.
return new class extends Migration
{
    public function up()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('factory_id')->nullable()->after('role_id');
            $table->foreign('factory_id', 'fk_permissions_factory')->references('id')->on('factories');
            $table->index('factory_id', 'idx_permissions_factory');
        });
    }

    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign('fk_permissions_factory');
            $table->dropIndex('idx_permissions_factory');
            $table->dropColumn('factory_id');
        });
    }
};
