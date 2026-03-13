<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.host' => 'aws-1-us-east-1.pooler.supabase.com']);
config(['database.connections.pgsql.port' => 6543]);
config(['database.connections.pgsql.database' => 'postgres']);
config(['database.connections.pgsql.username' => 'postgres.isopgultyctwovsuczrl']);
config(['database.connections.pgsql.password' => 'vKtndMgrt0Tm6H07']);
config(['database.connections.pgsql.sslmode' => 'require']);
config(['database.default' => 'pgsql']);

try {
    \Illuminate\Support\Facades\DB::table('migrations')->insert(['migration' => '2026_03_11_215120_add_fulltext_index_to_memories_table', 'batch' => 5]);
    echo "Marked migration as completed manually.\n";
} catch (\Exception $e) {
    echo 'Failed: '.$e->getMessage()."\n";
}
