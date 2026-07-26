<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Season uniqueness moves from (customer_id, name) + a global unique on code,
// to a single (customer_id, code) composite key — two customers may reuse the
// same season code, but not the same customer.
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE seasons DROP INDEX uniq_seasons_customer_name');
        DB::statement('ALTER TABLE seasons DROP INDEX uniq_seasons_code');
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT uniq_seasons_customer_code UNIQUE (customer_id, code)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE seasons DROP INDEX uniq_seasons_customer_code');
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT uniq_seasons_code UNIQUE (code)');
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT uniq_seasons_customer_name UNIQUE (customer_id, name)');
    }
};
