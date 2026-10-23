<?php

// Explicit disposable fixture: never resolves to the ordinary application database.
require __DIR__.'/../vendor/autoload.php';

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ComposePlan;
use App\Actions\Planner\ConfigureExecutionPolicy;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ExecuteLivingOccurrence;
use App\Actions\Planner\ManagePurposes;
use App\Actions\Planner\PersonalExperiment;
use App\Actions\Planner\ReconcileOccurrenceWindows;
use App\Actions\Planner\ReuseRoutine;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Concept;
use App\PlanScheduleFrequency;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$driver = config('database.default');
$target = config('database.connections.'.$driver.'.database');
if (! app()->environment('local', 'testing') || ($driver === 'mysql'
    ? $target !== 'iet_living_planner_demo'
    : ($driver !== 'sqlite' || basename((string) $target) !== 'living-planner-demo.sqlite'))) {
    throw new RuntimeException('Only the explicitly named isolated living-planner-demo database is allowed.');
}
Artisan::call('migrate', ['--force' => true]);
if (DB::table('users')->exists()) {
    echo "Demo already contains accounts; preserved without reseeding.\n";
    exit(0);
}
$directory = storage_path('release-acceptance/living');
@mkdir($directory, 0777, true);
$password = bin2hex(random_bytes(16));
$owner = Actor::factory()->create()->user;
$other = Actor::factory()->create()->user;
foreach ([$owner, $other] as $index => $user) {
    $user->forceFill(['email' => $index === 0 ? 'planner-owner@example.test' : 'planner-participant@example.test',
        'email_verified_at' => now(), 'locale' => 'en', 'timezone' => 'Asia/Tehran', 'password' => Hash::make($password)])->save();
    app(FeatureSurfaceGrantService::class)->sync($user, ['planner'], null);
}
$context = app(EnsurePersonalContext::class)->execute($owner);
$root = app(CreatePlan::class)->execute($context, $owner, 'Morning routine', timezone: 'Asia/Tehran');
$purpose = app(ManagePurposes::class)->create($owner, 'A calmer, healthier morning', 'Build a sustainable routine.', now()->addMonths(3)->toDateString());
app(ManagePurposes::class)->link($owner, $purpose, $root);
$realNow = CarbonImmutable::now('UTC');
$today = $realNow->setTimezone('Asia/Tehran')->startOfDay();
$exercise = null;
foreach (['Wake up', 'Exercise', 'Breakfast', 'Cleaning', 'Prepare for work', 'Forgotten reading'] as $index => $title) {
    $plan = app(CreatePlan::class)->execute($context, $owner, $title, timezone: 'Asia/Tehran');
    app(ComposePlan::class)->execute($owner, $plan, $root);
    app(ConfigureExecutionPolicy::class)->execute($owner, $plan, $index === 4 ? 'hard' : 'flexible');
    $rule = app(CreatePlanScheduleRule::class)->execute($plan, $owner, PlanScheduleFrequency::Daily, $today->subDays(7)->toDateString(), sprintf('%02d:00', 6 + $index), 30, windowAfterMinutes: 60);
    foreach ($rule->occurrences()->where('scheduled_end_at', '<', $realNow)->get() as $occurrence) {
        if ($index === 5) {
            continue;
        }
        $delay = max(2, 20 - (int) $today->subDays(7)->diffInDays($occurrence->scheduled_start_at));
        CarbonImmutable::setTestNow($occurrence->scheduled_start_at->addMinutes($delay));
        $act = app(ExecuteLivingOccurrence::class);
        $act->execute($owner, $occurrence, 'start', 'demo-start-'.$occurrence->id);
        CarbonImmutable::setTestNow($occurrence->scheduled_start_at->addMinutes($delay + 15));
        $act->execute($owner, $occurrence, 'finish', 'demo-finish-'.$occurrence->id,
            ['outcome' => $index === 1 ? 'partial' : ($index === 3 ? 'interrupted' : 'completed')]);
    }
    CarbonImmutable::setTestNow();
    if ($index === 1) {
        $exercise = $plan;
    }
}
app(ReuseRoutine::class)->capture($owner, $root, 'My reusable morning');
$relationship = app(CreateRelationship::class)->execute($owner, Concept::factory()->create(), 'participant', [['actor' => $other->actor, 'role' => 'participant']]);
$relationship = app(RespondToRelationship::class)->execute($relationship, $other, true);
$shared = app(CreatePlan::class)->execute($relationship->contextBinding->context, $owner, 'Shared preparation', timezone: 'Asia/Tehran', participants: [['actor' => $other->actor]]);
app(ConfigureExecutionPolicy::class)->execute($owner, $shared, 'flexible');
$rule = app(CreatePlanScheduleRule::class)->execute($shared, $owner, PlanScheduleFrequency::Once, $today->subDay()->toDateString(), '08:00', 30);
$occurrence = $rule->occurrences()->sole();
$act = app(ExecuteLivingOccurrence::class);
$act->execute($owner, $occurrence, 'report', 'demo-shared-report', ['outcome' => 'completed',
    'actual_start_at' => $occurrence->scheduled_start_at->toIso8601String(), 'actual_end_at' => $occurrence->scheduled_end_at->toIso8601String()]);
$act->execute($other, $occurrence, 'report', 'demo-other-report', ['outcome' => 'missed']);
$prior = $occurrence->events()->where('actor_id', $owner->actor->id)->where('event_type', 'outcome_reported')->sole();
$act->execute($owner, $occurrence, 'correct', 'demo-shared-correction', ['outcome' => 'partial',
    'actual_start_at' => $occurrence->scheduled_start_at->toIso8601String(), 'actual_end_at' => $occurrence->scheduled_start_at->addMinutes(15)->toIso8601String(),
    'supersedes' => $prior->uuid, 'reason' => 'Corrected from my activity notes.']);
$service = app(PersonalExperiment::class);
$experiment = $service->create($owner, ['title' => 'A smaller start', 'hypothesis' => 'A short exercise session may be easier to begin.',
    'selected_change' => 'Prepare shoes the night before', 'metric' => 'active_minutes', 'baseline' => 5, 'target' => 15,
    'starts_on' => $today->subDays(3)->toDateString(), 'ends_on' => $today->addDays(3)->toDateString()], plan: $exercise);
foreach ([8 => 5, 6 => 6, 2 => 12, 1 => 15] as $daysAgo => $value) {
    $service->observe($owner, $experiment, 'demo-observe-'.$daysAgo, ['observed_at' => $today->subDays($daysAgo)->addHours(8)->toIso8601String(),
        'value' => $value, 'reflection' => 'Private demo reflection.']);
}
$adjustment = $service->propose($owner, $experiment, 'Try a 20-minute exercise slot next week.');
$service->decide($owner, $adjustment, true);
while (app(ReconcileOccurrenceWindows::class)->execute(20) > 0) {
    // Exercise bounded catch-up as if the scheduler had been absent.
}
file_put_contents($directory.'/accounts.json', json_encode(['owner' => $owner->email, 'participant' => $other->email, 'password' => $password], JSON_PRETTY_PRINT));
echo "Isolated living demo ready. Credentials are in ignored storage/release-acceptance/living/accounts.json.\n";
