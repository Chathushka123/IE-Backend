<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An SMV study times several operators on one operation. Each operator's run is an ordinary
// time_studies row; the rows of one study share a session_id. Only the first row (session_lead) is
// listed in the review queue, and one decision covers the whole session. rating_pct is the
// operator's performance rating; proposed_smv (minutes) is the same on every row of the session.
// Every existing row is a single study: no session, and it is its own lead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->char('session_id', 36)->nullable()->after('field_values');
            $table->boolean('session_lead')->default(true)->after('session_id');
            $table->decimal('rating_pct', 5, 1)->nullable()->after('session_lead');
            $table->decimal('proposed_smv', 8, 4)->nullable()->after('rating_pct');

            $table->index('session_id', 'idx_time_studies_session');
        });
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropIndex('idx_time_studies_session');
            $table->dropColumn(['session_id', 'session_lead', 'rating_pct', 'proposed_smv']);
        });
    }
};
