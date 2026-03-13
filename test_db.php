<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.host' => getenv('DB_HOST')]);
config(['database.connections.pgsql.port' => getenv('DB_PORT')]);
config(['database.connections.pgsql.database' => getenv('DB_DATABASE')]);
config(['database.connections.pgsql.username' => getenv('DB_USERNAME')]);
config(['database.connections.pgsql.password' => getenv('DB_PASSWORD')]);
config(['database.connections.pgsql.sslmode' => getenv('DB_SSLMODE')]);
config(['database.default' => 'pgsql']);

$start = microtime(true);
$count = \App\Models\Memory::count();
$end = microtime(true);

echo "Count: $count\n";
echo 'DB Count took: '.($end - $start)." seconds\n";
