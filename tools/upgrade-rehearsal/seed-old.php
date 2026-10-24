<?php

/*
 * Adds representative records to an OLD release's database before the
 * upgrade rehearsal, using only that release's own code: skills (people and
 * business members), Needs/Offers, business offers, service agreements,
 * bookkeeping (including a correction) and group chat. Defensive: skips what
 * the old version does not have.
 *
 * php tools/upgrade-rehearsal/seed-old.php <old-app-dir>
 */

$appDir = $argv[1];
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\RecordAdjustment;
use App\Actions\Bookkeeping\RecordPersonalEntry;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Actions\Conversations\ReviseConversationMessage;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\GroupSpace;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$now = now();
$say = fn (string $line) => fwrite(STDOUT, $line."\n");

$actors = DB::table('actors')->orderBy('id')->limit(6)->pluck('id')->all();
$professions = DB::table('professions')->orderBy('id')->limit(12)->pluck('id')->all();
$say('actors='.count($actors).' professions='.count($professions));

// People's old skills, including one that the new catalog does not map (kept as a legacy category).
foreach (array_slice($actors, 0, 4) as $i => $actorId) {
    foreach (array_slice($professions, $i, 2) as $j => $professionId) {
        DB::table('actor_professions')->insertOrIgnore([
            'actor_id' => $actorId, 'profession_id' => $professionId,
            'level' => ['beginner', 'intermediate', 'advanced', 'expert'][($i + $j) % 4] ?? null,
            'years_experience' => $i + $j + 1, 'is_primary' => $j === 0,
            'visibility' => ['public', 'private', 'contacts'][$i % 3] ?? 'public',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
$custom = DB::table('professions')->insertGetId([
    'code' => 'rehearsal-unmapped-skill', 'name' => 'Rehearsal unmapped skill', 'name_fa' => 'مهارت آزمایشی بدون نگاشت',
    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
]);
DB::table('actor_professions')->insertOrIgnore(['actor_id' => $actors[0], 'profession_id' => $custom, 'visibility' => 'public', 'created_at' => $now, 'updated_at' => $now]);

// Business members' skills.
foreach (DB::table('business_memberships')->orderBy('id')->limit(4)->pluck('id') as $k => $membershipId) {
    DB::table('business_membership_profession')->insertOrIgnore([
        'business_membership_id' => $membershipId, 'profession_id' => $professions[$k] ?? $custom,
        'is_primary' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
}
$say('actor_professions='.DB::table('actor_professions')->count().' membership_professions='.DB::table('business_membership_profession')->count());

// Bookkeeping: a personal expense, and a shared book with a debt, a payment and a correction.
$superadmin = User::query()->where('username', config('bootstrap.superadmin.username', 'MrEveryThing'))->first();
$other = User::query()->whereKeyNot($superadmin?->id)->whereNotNull('email_verified_at')->where('status', 'active')->whereHas('actor')->orderBy('id')->first();
if ($superadmin && $other && class_exists(CreateLedger::class)) {
    $personal = app(EnsurePersonalLedger::class)->execute($superadmin);
    app(RecordPersonalEntry::class)->execute($personal, $superadmin, TransactionType::Expense, '250000', $now->toDateString(), $personal->wallets()->firstOrFail(), description: 'Rehearsal personal expense');

    $book = app(CreateLedger::class)->execute($superadmin, LedgerKind::Shared, 'Rehearsal shared book');
    app(AddLedgerParty::class)->execute($book, $superadmin, $other->username, null);
    $parties = $book->parties()->get()->keyBy('actor_id');
    $me = $parties->get($superadmin->actor->id);
    $them = $parties->get($other->actor->id);
    $debt = app(RecordSharedEntry::class)->execute($book, $superadmin, TransactionType::Obligation, $them, $me, '1000000', $now->toDateString(), 'Rehearsal debt');
    app(DecideOnEntry::class)->approve($debt->fresh(), $other);
    app(RecordSharedEntry::class)->execute($book, $other, TransactionType::Payment, $them, $me, '400000', $now->toDateString(), 'Rehearsal payment');
    app(RecordAdjustment::class)->execute($debt->fresh(), $superadmin, AdjustmentDirection::Decrease, '50000', 'Rehearsal correction');
    $say('bk_transactions='.DB::table('bk_transactions')->count());
}

// Group chat with a reply and an edit.
if ($superadmin && class_exists(CreateGroup::class) && class_exists(PostGroupSpaceMessage::class)) {
    $group = app(CreateGroup::class)->execute($superadmin->actor, 'Rehearsal group', 'Upgrade rehearsal');
    $space = GroupSpace::query()->where('group_id', $group->id)->where('kind', 'chat')->first();
    if ($space) {
        $first = app(PostGroupSpaceMessage::class)->execute($space, $superadmin, 'First message before the upgrade');
        app(PostGroupSpaceMessage::class)->execute($space, $superadmin, 'A reply before the upgrade', $first->id);
        if (class_exists(ReviseConversationMessage::class)) {
            app(ReviseConversationMessage::class)->edit($first, $superadmin, 'First message (edited before the upgrade)');
        }
    }
    $say('conversation_messages='.DB::table('conversation_messages')->count());
}

$say('intents='.DB::table('actor_profile_intents')->count().' business offers='.DB::table('actor_profile_intents')->where('metadata', 'like', '%business_listing_version%')->count());
