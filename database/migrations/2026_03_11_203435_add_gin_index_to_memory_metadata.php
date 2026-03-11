<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX memories_tags_gin_idx ON memories USING GIN ((metadata->'tags'));");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS memories_tags_gin_idx;');
    }
};
