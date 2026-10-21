<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Home\Today;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Models\Actor;
use App\Models\NotificationOutbox;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PushSubscription;
use App\Notifications\ImportantReminderMail;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\Push\Base64Url;
use App\Support\Push\DevicePushDispatcher;
use App\Support\Push\EcKeys;
use App\Support\Push\VapidKeys;
use App\Support\Push\WebPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class PlannerRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = VapidKeys::generate();
        // Configured keys, so the test never writes generated keys to storage.
        config(['webpush.public_key' => $keys['public'], 'webpush.private_key' => $keys['private_pem'], 'webpush.subject' => 'mailto:test@example.com']);
        VapidKeys::forgetCached();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        VapidKeys::forgetCached();
        parent::tearDown();
    }

    public function test_monthly_plans_follow_the_persian_calendar(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();
        $me->user->forceFill(['timezone' => 'Asia/Tehran', 'calendar' => 'persian'])->save();
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($me->user), $me->user, 'Loan installment', metadata: ['kind' => 'due', 'important' => true]);

        // 5 Aban 1405 = 27 October 2026.
        app(CreatePlanScheduleRule::class)->execute($plan, $me->user, PlanScheduleFrequency::Monthly, '2026-10-27', '09:00', 15, occurrenceLimit: 3, monthDay: 5);

        $days = $plan->occurrences()->orderBy('scheduled_start_at')->get()
            ->map(fn (PlanOccurrence $occurrence): string => $occurrence->scheduled_start_at->setTimezone('Asia/Tehran')->format('Y-m-d'))->all();
        // 5 Aban, 5 Azar, 5 Dey.
        $this->assertSame(['2026-10-27', '2026-11-26', '2026-12-26'], $days);
    }

    public function test_important_due_dates_stay_on_today_until_marked_done(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($me->user), $me->user, 'Cheque to Ali', metadata: ['kind' => 'due', 'important' => true]);
        app(CreatePlanScheduleRule::class)->execute($plan, $me->user, PlanScheduleFrequency::Once, '2026-10-25', '10:00', 15);
        $occurrence = $plan->occurrences()->sole();

        $today = Livewire::actingAs($me->user)->test(Today::class)
            ->assertSeeHtml('data-today-dont-forget')
            ->assertSee('Cheque to Ali');

        $today->call('markDone', $occurrence->uuid)->assertDontSeeHtml('data-today-dont-forget');
        $this->assertSame(PlanOccurrenceStatus::Completed, $occurrence->fresh()->status);
    }

    public function test_the_wizard_saves_monthly_important_plans_and_returns_to_the_step_with_a_problem(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();

        Livewire::actingAs($me->user)->test(PlannerCreate::class)
            ->call('chooseKind', 'due')
            ->assertSet('important', true)
            ->set('title', 'Rent')
            ->set('selectedDays', ['2026-10-25'])
            ->set('startsOn', '2026-10-25')
            ->set('startTime', '25:99')
            ->set('step', 'review')
            ->call('save')
            ->assertHasErrors('startTime')
            ->assertSet('step', 'when')
            ->assertSeeHtml('data-wizard-errors')
            ->set('startTime', '09:00')
            ->call('setRepeat', 'monthly')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()->with('scheduleRules')->sole();
        $this->assertTrue($plan->metadata['important']);
        $this->assertSame(PlanScheduleFrequency::Monthly, $plan->scheduleRules->sole()->frequency);
    }

    public function test_push_payloads_are_encrypted_for_the_device_only(): void
    {
        [$private, $public, $auth] = $this->deviceKeys();
        $body = app(WebPushSender::class)->encrypt('{"title":"قسط"}', $public, $auth);

        $this->assertSame('{"title":"قسط"}', $this->decrypt($body, $private, $public, $auth));
    }

    public function test_reminders_are_pushed_once_to_devices_and_important_ones_are_emailed(): void
    {
        Notification::fake();
        Http::fake(['https://push.example.test/*' => Http::response('', 201), 'https://gone.example.test/*' => Http::response('', 410)]);
        $me = Actor::factory()->create();
        [$private, $public, $auth] = $this->deviceKeys();
        $this->subscribe($me->user->id, 'https://push.example.test/abc', $public, $auth);
        $gone = $this->subscribe($me->user->id, 'https://gone.example.test/old', $public, $auth);

        NotificationOutbox::query()->create([
            'recipient_user_id' => $me->user->id,
            'dedupe_key' => 'planner:test:1',
            'kind' => 'planner.reminder',
            'data' => ['title_key' => 'notifications.device.test_title', 'url' => '/planner', 'important' => true],
            'requested_at' => now(),
            'dispatched_at' => now(),
        ]);

        $dispatcher = app(DevicePushDispatcher::class);
        $this->assertSame(1, $dispatcher->run());
        $this->assertSame(0, $dispatcher->run());

        $this->assertModelMissing($gone);
        Http::assertSent(function (ClientRequest $request) use ($private, $public, $auth): bool {
            if ($request->url() !== 'https://push.example.test/abc') {
                return false;
            }
            $payload = json_decode($this->decrypt($request->body(), $private, $public, $auth), true);

            return str_starts_with($request->header('Authorization')[0], 'vapid t=') && $payload['important'] === true;
        });
        Notification::assertSentTo($me->user, ImportantReminderMail::class);
    }

    public function test_devices_subscribe_and_unsubscribe(): void
    {
        $me = Actor::factory()->create();
        [, $public, $auth] = $this->deviceKeys();
        $subscription = ['endpoint' => 'https://push.example.test/me', 'keys' => ['p256dh' => Base64Url::encode($public), 'auth' => Base64Url::encode($auth)]];

        $this->actingAs($me->user)->postJson(route('push.subscriptions.store'), $subscription)->assertOk()->assertJson(['devices' => 1]);
        $this->actingAs($me->user)->postJson(route('push.subscriptions.store'), $subscription)->assertOk()->assertJson(['devices' => 1]);
        $this->actingAs($me->user)->postJson(route('push.subscriptions.store'), ['endpoint' => 'http://insecure.test/x', 'keys' => $subscription['keys']])->assertStatus(422);
        $this->actingAs($me->user)->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => $subscription['endpoint']])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->app['auth']->forgetGuards();
        $this->postJson(route('push.subscriptions.store'), $subscription)->assertUnauthorized();
    }

    /** @return array{0: OpenSSLAsymmetricKey, 1: string, 2: string} */
    private function deviceKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);

        return [$key, EcKeys::rawPublic(openssl_pkey_get_details($key)), random_bytes(16)];
    }

    private function subscribe(int $userId, string $endpoint, string $public, string $auth): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => Base64Url::encode($public),
            'auth_token' => Base64Url::encode($auth),
        ]);
    }

    /** RFC 8291 decryption, as a browser would do it. */
    private function decrypt(string $body, OpenSSLAsymmetricKey $private, string $public, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $idLength = ord($body[20]);
        $serverPublic = substr($body, 21, $idLength);
        $cipher = substr($body, 21 + $idLength);

        $shared = openssl_pkey_derive(EcKeys::publicKeyFromRaw($serverPublic), $private, 32);
        $this->assertIsString($shared);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$public.$serverPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
        $this->assertIsString($plain);

        return substr($plain, 0, (int) strrpos($plain, "\x02"));
    }
}
