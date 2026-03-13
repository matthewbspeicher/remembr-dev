<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\DB::statement("SET maintenance_work_mem = '128MB'");
    echo "Successfully set maintenance_work_mem\n";
} catch (\Exception $e) {
    echo 'Failed: '.$e->getMessage()."\n";
}
