<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX memories_metadata_gin_idx ON memories USING GIN (metadata jsonb_path_ops);');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS memories_metadata_gin_idx;');
    }
};
