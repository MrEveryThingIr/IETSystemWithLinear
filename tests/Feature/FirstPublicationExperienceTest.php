<?php

namespace Tests\Feature;

use App\Actions\Auth\ProvisionVerifiedUserDefaults;
use App\Enums\Bookkeeping\LedgerKind;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Profile\TemporalPreferences;
use App\Models\Bookkeeping\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class FirstPublicationExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_provisions_an_idempotent_personal_book(): void
    {
        $user = User::factory()->unverified()->create([
            'default_monetary_unit_code' => 'USD',
        ]);
        $user->actor()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->get($url)->assertRedirect(route('dashboard'));

        $ledger = Ledger::query()->where('owner_actor_id', $user->refresh()->actor->id)->sole();
        $this->assertSame(LedgerKind::Personal, $ledger->kind);
        $this->assertSame('IRT', $ledger->currency);
        $this->assertSame(1, $ledger->wallets()->count());
        $this->assertTrue($ledger->categories()->where('system_key', 'food')->exists());
        $this->assertDatabaseCount('bk_ledgers', 1);
        $this->assertDatabaseCount('bk_transactions', 0);
    }

    public function test_default_currency_can_change_without_touching_the_toman_book(): void
    {
        $user = User::factory()->create([
            'default_monetary_unit_code' => 'USD',
        ]);
        $user->actor()->create();

        app(ProvisionVerifiedUserDefaults::class)->execute($user->refresh());

        Livewire::actingAs($user->refresh())
            ->test(TemporalPreferences::class)
            ->call('openEditor')
            ->set('defaultMonetaryUnitCode', 'IRR')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('IRR', $user->refresh()->default_monetary_unit_code);
        $this->assertSame('IRT', Ledger::query()->where('owner_actor_id', $user->actor->id)->sole()->currency);
        $this->assertDatabaseCount('bk_ledgers', 1);
    }

    public function test_calendar_drills_from_year_to_month_to_day_to_minute_slots_and_prefills_exact_plan_time(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tehran']);
        $user->actor()->create();

        Livewire::actingAs($user)
            ->test(PlannerIndex::class)
            ->set('view', 'calendar')
            ->call('showYear', '2026')
            ->assertSet('calendarLevel', 'year')
            ->assertSee('2026')
            ->call('showMonth', '2026-09')
            ->assertSet('calendarLevel', 'month')
            ->call('showDay', '2026-09-26')
            ->assertSet('calendarLevel', 'day')
            ->assertSee('00:00')
            ->assertSee(__('planner.calendar.add_to_day'))
            ->call('showHour', '2026-09-26', 14)
            ->assertSet('calendarLevel', 'hour')
            ->assertSet('hour', 14)
            ->assertSee('14:00')
            ->call('setSlotMinutes', 5)
            ->assertSet('slotMinutes', 5)
            ->assertSee('14:05')
            ->call('setSlotMinutes', 1)
            ->assertSet('slotMinutes', 1)
            ->assertSee('14:01');

        Livewire::actingAs($user)
            ->withQueryParams([
                'date' => '2026-09-26',
                'time' => '14:37',
                'duration' => 5,
            ])
            ->test(PlannerCreate::class)
            ->assertSet('startsOn', '2026-09-26')
            ->assertSet('startTime', '14:37')
            ->assertSet('durationMinutes', 5);
    }

    public function test_localized_header_contains_live_temporal_status_and_no_raw_quick_link_keys(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['locale' => 'fa', 'timezone' => 'Asia/Tehran']);
        $user->actor()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('iet-ambient-status', false)
            ->assertSee(__('home.title'))
            ->assertDontSee('home.title', false);
    }
}
