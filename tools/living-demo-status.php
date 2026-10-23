<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'iet_living_planner_demo') {
    throw new RuntimeException('Only the isolated demo may be inspected.');
}
echo json_encode(['migrations' => DB::table('migrations')->count(),
    'users' => DB::table('users')->count(), 'plans' => DB::table('plans')->count()], JSON_PRETTY_PRINT).PHP_EOL;
