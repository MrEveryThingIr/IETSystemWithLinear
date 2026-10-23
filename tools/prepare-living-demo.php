<?php

// Opt-in local setup. Creates only the named demo database and edits this checkout's ignored .env.
require __DIR__.'/../vendor/autoload.php';
$source = $argv[1] ?? '';
if (! is_file($source)) {
    throw new RuntimeException('Supply the existing local MySQL .env path.');
}
$settings = Dotenv\Dotenv::parse(file_get_contents($source));
if (($settings['APP_ENV'] ?? '') !== 'local' || ($settings['DB_CONNECTION'] ?? '') !== 'mysql') {
    throw new RuntimeException('Expected an explicitly local MySQL source environment.');
}
$host = $settings['DB_HOST'] ?? '127.0.0.1';
if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Demo preparation requires a loopback database server.');
}
$pdo = new PDO('mysql:host='.$host.';port='.($settings['DB_PORT'] ?? '3306'), $settings['DB_USERNAME'] ?? '', $settings['DB_PASSWORD'] ?? '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE IF NOT EXISTS iet_living_planner_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$path = __DIR__.'/../.env';
$environment = file_get_contents($path);
foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $settings['DB_PORT'] ?? '3306',
    'DB_DATABASE' => 'iet_living_planner_demo', 'DB_USERNAME' => $settings['DB_USERNAME'] ?? '', 'DB_PASSWORD' => $settings['DB_PASSWORD'] ?? '',
    'DB_URL' => '', 'APP_URL' => 'http://127.0.0.1:8002', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
    'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    $line = $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    $environment = preg_match('/^'.preg_quote($key, '/').'=/m', $environment)
        ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $environment)
        : $environment.PHP_EOL.$line.PHP_EOL;
}
file_put_contents($path, $environment);
echo "Prepared isolated MySQL database iet_living_planner_demo for this checkout. Existing database and APP_KEY preserved.\n";
