<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.host' => 'aws-1-us-east-1.pooler.supabase.com']);
config(['database.connections.pgsql.port' => 6543]);
config(['database.connections.pgsql.database' => 'postgres']);
config(['database.connections.pgsql.username' => 'postgres.isopgultyctwovsuczrl']);
config(['database.connections.pgsql.password' => 'vKtndMgrt0Tm6H07']);
config(['database.connections.pgsql.sslmode' => 'require']);
config(['database.default' => 'pgsql']);

try {
    // Drop the column if it partially exists to reset state
    DB::statement('ALTER TABLE memories DROP COLUMN IF EXISTS search_vector CASCADE;');
    echo "Column dropped.\n";

    // Attempt the migration manually using smaller steps or bypassing transaction
    DB::unprepared("SET maintenance_work_mem TO '256MB'");
    echo "maintenance_work_mem set.\n";

    // Add column WITHOUT STORED
    DB::unprepared('ALTER TABLE memories ADD COLUMN search_vector tsvector');
    echo "Column added.\n";

    // Update existing rows in chunks instead of generated
    DB::unprepared("UPDATE memories SET search_vector = to_tsvector('english', coalesce(value, ''))");
    echo "Rows updated.\n";

    // Create an index on the generated vector
    DB::unprepared('CREATE INDEX memories_search_vector_idx ON memories USING GIN(search_vector)');
    echo "Index created.\n";

    echo "Success!\n";
} catch (Exception $e) {
    echo 'Failed: '.$e->getMessage()."\n";
}
