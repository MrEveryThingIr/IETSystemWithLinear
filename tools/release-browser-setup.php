<?php

use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\RecordPersonalEntry;
use App\Actions\Messaging\OpenDirectConversation;
use App\Actions\Messaging\SendMessage;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use App\Services\Surfaces\FeatureSurfaceRegistry;
use Database\Seeders\CoherenceBaselineDemoSeeder;
use Database\Seeders\PersonalPlannerDemoSeeder;
use Database\Seeders\Phase7InteractionDemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

// Isolated browser-acceptance database: never uses the configured user database.
require __DIR__.'/../vendor/autoload.php';
$directory = __DIR__.'/../storage/release-acceptance';
@mkdir($directory, 0777, true);
$database = $directory.'/browser.sqlite';
if (! is_file($database)) {
    touch($database);
}
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.realpath($database));
putenv('DB_URL=');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=file');
putenv('MAIL_MAILER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('BROADCAST_CONNECTION=null');
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== realpath($database)) {
    throw new RuntimeException('Isolated database configuration was overridden.');
}
Artisan::call('migrate', ['--force' => true]);
$actor = Actor::first() ?? Actor::factory()->create();
$actor->user->forceFill([
    'email' => 'release-browser@example.test',
    'password' => Hash::make('Release-browser-fixture-2026!'),
    'locale' => 'en',
])->save();
config([
    'bootstrap.superadmin.username' => 'release-superadmin',
    'bootstrap.superadmin.email' => 'release-admin@example.test',
    'bootstrap.superadmin.password' => 'Release-browser-fixture-2026!',
    'bootstrap.demo_data' => false,
]);
foreach ([
    CoherenceBaselineDemoSeeder::class,
    PersonalPlannerDemoSeeder::class,
    Phase7InteractionDemoSeeder::class,
] as $seeder) {
    Model::unguarded(fn () => app($seeder)->run());
}
$surfaces = app(FeatureSurfaceRegistry::class)->grantableKeys();
foreach (User::all() as $user) {
    $user->forceFill(['password' => Hash::make('Release-browser-fixture-2026!'),
        'email_verified_at' => now(), 'message_privacy' => 'everyone'])->save();
    app(FeatureSurfaceGrantService::class)->sync($user, $surfaces, null);
}
$other = User::where('email', 'release-admin@example.test')->firstOrFail();
$conversation = app(OpenDirectConversation::class)->execute($actor->user, $other->actor);
if (! $conversation->messages()->exists()) {
    app(SendMessage::class)->execute($actor->user, $conversation, 'Please confirm the inspection appointment and invoice.');
    app(SendMessage::class)->execute($other, $conversation, 'Confirmed for tomorrow. The service agreement is ready for review.');
}
foreach ([$actor->user, $other] as $user) {
    $ledger = app(EnsurePersonalLedger::class)->execute($user);
    app(RecordPersonalEntry::class)->execute(
        $ledger, $user, TransactionType::Expense, 250000,
        now()->toDateString(), $ledger->wallets()->firstOrFail(),
        description: 'Local release acceptance: transport to client inspection',
        idempotencyKey: 'release-browser-transport',
    );
}
$counts = [];
foreach (['users', 'businesses', 'groups', 'plans', 'contracts', 'conversation_messages', 'bk_transactions'] as $table) {
    if (Schema::hasTable($table)) {
        $counts[$table] = DB::table($table)->count();
    }
}
file_put_contents($directory.'/fixture-counts.json', json_encode($counts, JSON_PRETTY_PRINT));
echo 'Fixture user created in isolated SQLite database.'.PHP_EOL;
