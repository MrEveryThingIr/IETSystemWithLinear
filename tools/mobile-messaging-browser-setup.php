<?php

use App\Actions\Messaging\OpenDirectConversation;
use App\Actions\Messaging\SendMessage;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

// UI-only fixture. Never connects to the configured development or production database.
require __DIR__.'/../vendor/autoload.php';
$dir = __DIR__.'/../storage/release-acceptance/v1.12-mobile-messaging-shell';
@mkdir($dir, 0777, true);
$db = $dir.'/browser.sqlite';
@mkdir($dir.'/views', 0777, true);
putenv('VIEW_COMPILED_PATH='.realpath($dir.'/views'));
if (! is_file($db)) {
    touch($db);
}
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => realpath($db), 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'mobile_shell_acceptance', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
    putenv($key.'='.$value);
}
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== realpath($db)) {
    throw new RuntimeException('Fixture database mismatch');
}
Artisan::call('migrate', ['--force' => true]);
$users = [];
foreach (['mobile-shell-viewer', 'mobile-shell-other'] as $name) {
    $user = User::query()->where('username', $name)->first();
    if (! $user) {
        $user = User::factory()->create(['username' => $name, 'email' => $name.'@example.test', 'password' => Hash::make('Mobile-shell-fixture-2026!')]);
    }
    $user->forceFill(['message_privacy' => 'everyone', 'locale' => 'en'])->save();
    $user->actor()->firstOrCreate([]);
    app(FeatureSurfaceGrantService::class)->sync($user, ['messages', 'market', 'planner'], null);
    $users[] = $user->fresh('actor');
}
$conversation = app(OpenDirectConversation::class)->execute($users[0], $users[1]->actor);
if (! $conversation->messages()->exists()) {
    for ($i = 0; $i < 36; $i++) {
        app(SendMessage::class)->execute($users[$i % 2], $conversation, 'Mobile shell history '.$i.' — پیام برای آزمون پیمایش');
    }
    app(SendMessage::class)->execute($users[1], $conversation, str_repeat('LongUnbrokenMessage', 45));
}
file_put_contents($dir.'/fixture.json', json_encode(['conversation' => $conversation->uuid, 'context' => $conversation->context->uuid]));
echo 'Isolated messaging fixture ready.'.PHP_EOL;
