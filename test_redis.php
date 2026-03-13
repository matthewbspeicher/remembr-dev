<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.redis.client' => 'predis']);
config(['database.redis.default.url' => getenv('REDIS_URL')]);
config(['database.redis.cache.url' => getenv('REDIS_URL')]);
config(['cache.default' => 'redis']);

$start = microtime(true);
cache()->get('test');
$end = microtime(true);

echo 'Cache Get took: '.($end - $start)." seconds\n";
