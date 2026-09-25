<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Profile\TemporalPreferences;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class FirstPublicationExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_provisions_an_idempotent_personal_wallet_foundation(): void
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

        $context = app(EnsurePersonalContext::class)->execute($user->refresh());
        $ledger = $context->ledgers()->with(['monetaryUnit', 'accounts'])->sole();
        $this->assertSame('USD', $ledger->monetaryUnit->code);
        $this->assertEqualsCanonicalizing(
            ['cash', 'general_expense', 'general_income', 'opening_equity'],
            $ledger->accounts->pluck('system_key')->all(),
        );
        $this->assertDatabaseCount('ledgers', 1);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_default_currency_can_change_without_converting_existing_history(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        Livewire::actingAs($user->refresh())
            ->test(TemporalPreferences::class)
            ->call('openEditor')
            ->set('defaultMonetaryUnitCode', 'IRR')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('IRR', $user->refresh()->default_monetary_unit_code);
        $this->assertDatabaseHas('monetary_units', ['code' => 'IRR']);
        $this->assertDatabaseCount('ledgers', 1);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_calendar_drills_from_year_to_month_to_day_and_prefills_new_plan_date(): void
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
            ->assertSee(__('planner.calendar.add_to_day'));

        Livewire::actingAs($user)
            ->withQueryParams(['date' => '2026-09-26'])
            ->test(PlannerCreate::class)
            ->assertSet('startsOn', '2026-09-26');
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
            ->assertSee(__('home.quick_links'))
            ->assertDontSee('home.quick_links', false);
    }
}
