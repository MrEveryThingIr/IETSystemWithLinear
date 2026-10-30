<?php

// Runs the given PHPUnit paths on a freshly created, uniquely named local MySQL database and drops it afterwards.
// Never touches the owner's schema.  Usage: php tools/mysql-focused.php tests/Feature/SomeTest.php [more paths]
require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($c['url'])) {
    throw new RuntimeException('Local MySQL without URL override required.');
}
$paths = array_slice($argv, 1);
if ($paths === []) {
    throw new RuntimeException('Give at least one test path.');
}
$target = 'iet_focus_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/^iet_focus_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D', $target) || $target === $c['database']) {
    throw new RuntimeException('Unsafe disposable database name.');
}
$pdo = new PDO('mysql:host='.$c['host'].';port='.$c['port'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$env = [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $target, 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'], 'DB_URL' => '', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null'];
$exit = 1;
try {
    echo 'Disposable database: '.$target.PHP_EOL;
    $process = proc_open([PHP_BINARY, 'vendor/bin/phpunit', '--no-progress', ...$paths], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $env);
    $exit = proc_close($process);
} finally {
    $pdo->exec('DROP DATABASE `'.$target.'`');
    echo 'Dropped disposable database '.$target.PHP_EOL;
}
exit($exit);
