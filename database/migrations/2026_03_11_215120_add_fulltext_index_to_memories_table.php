<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("SET maintenance_work_mem TO '256MB'");
        DB::statement("ALTER TABLE memories ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(value, ''))) STORED");
        DB::statement("CREATE INDEX memories_search_vector_idx ON memories USING GIN(search_vector)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS memories_search_vector_idx");
        DB::statement("ALTER TABLE memories DROP COLUMN IF EXISTS search_vector");
    }
};
