<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every SMV a reviewer applied from an SMV study, so a change to a standard can always be traced to
// the evidence behind it and undone by hand. style_operation_id is set when the style's own override
// was changed instead of the operation's default. The unique session id means a session is applied
// at most once.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_smv_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('style_operation_id')->nullable();
            $table->decimal('old_smv', 8, 4)->nullable();
            $table->decimal('new_smv', 8, 4);
            $table->char('source_session_id', 36);
            $table->unsignedBigInteger('applied_by_id')->nullable();
            $table->dateTime('applied_at');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('source_session_id', 'uniq_operation_smv_history_session');
            $table->index('operation_id', 'idx_operation_smv_history_operation');
            $table->foreign('operation_id', 'fk_operation_smv_history_operation')->references('id')->on('operations');
            $table->foreign('style_operation_id', 'fk_operation_smv_history_style_operation')->references('id')->on('style_operations');
            $table->foreign('applied_by_id', 'fk_operation_smv_history_applied_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_smv_history');
    }
};
