<?php

namespace Tests\Feature;

use App\Livewire\Platform\DayTexts as DayTextsPage;
use App\Models\Actor;
use App\Models\DayText;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use App\Support\RequestMemo;
use App\Support\TimeBar\DayTexts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TimeBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_page_shows_the_full_screen_clock_and_the_bar_to_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-clock-stage', false)
            ->assertSee('data-clock-analog', false)
            ->assertSee('data-clock-gregorian', false)
            ->assertSee('data-time-bar', false)
            ->assertSee('data-timezone=""', false)
            ->assertSee(__('time_bar.default_text', [], 'en'));
    }

    public function test_default_text_is_written_in_every_language_and_follows_the_page_language(): void
    {
        $texts = [];
        foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
            $text = DayTexts::defaultText($locale);
            $this->assertNotSame('time_bar.default_text', $text);
            $texts[] = $text;
        }
        $this->assertCount(4, array_unique($texts));
        $this->assertStringContainsString('Everything for Everyone', $texts[0]);

        $this->withSession(['locale' => 'fa'])->get('/')
            ->assertOk()
            ->assertSee(DayTexts::defaultText('fa'))
            ->assertSee('data-calendar="persian"', false);
    }

    public function test_the_bar_is_on_signed_in_and_signed_out_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-time-bar', false);

        $user = $this->person();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-time-bar', false)
            ->assertSee('data-timezone="Asia/Tehran"', false);
    }

    public function test_only_the_superadmin_manages_the_text_of_the_day(): void
    {
        $this->get(route('platform.day-texts'))->assertRedirect(route('login'));
        $this->actingAs($this->person())->get(route('platform.day-texts'))->assertForbidden();
        $this->actingAs($this->superadmin())->get(route('platform.day-texts'))
            ->assertOk()
            ->assertSee(__('time_bar.admin.title'));
    }

    public function test_superadmin_writes_texts_and_the_bar_shows_them(): void
    {
        $admin = $this->superadmin();
        $today = CarbonImmutable::now('Asia/Tehran')->toDateString();

        Livewire::actingAs($admin)->test(DayTextsPage::class)
            ->call('create')
            ->set('kind', 'news')
            ->set('texts.en', 'The new market is open.')
            ->set('texts.fa', 'بازار تازه باز شد.')
            ->set('startsOn', $today)
            ->set('endsOn', $today)
            ->set('link', 'https://example.org/news')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);

        $this->assertDatabaseHas('day_texts', ['kind' => 'news', 'link_url' => 'https://example.org/news', 'created_by_user_id' => $admin->id]);
        RequestMemo::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('The new market is open.')
            ->assertSee('href="https://example.org/news"', false)
            ->assertDontSee(__('time_bar.default_text', [], 'en'));
        RequestMemo::flush();
        $this->withSession(['locale' => 'fa'])->get('/')->assertSee('بازار تازه باز شد.');
    }

    public function test_dated_texts_show_on_their_days_and_undated_texts_take_turns(): void
    {
        $day = CarbonImmutable::parse('2026-11-10 12:00', 'UTC');
        DayText::query()->create(['kind' => 'info', 'texts' => ['en' => 'Next week only'], 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-20']);
        DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'First quote'], 'sort_order' => 1]);
        DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'Second quote'], 'sort_order' => 2]);
        RequestMemo::flush();

        $first = array_column(DayTexts::today(null, 'en', $day), 'text');
        RequestMemo::flush();
        $second = array_column(DayTexts::today(null, 'en', $day->addDay()), 'text');
        RequestMemo::flush();
        $later = array_column(DayTexts::today(null, 'en', $day->addDays(8)), 'text');

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertNotSame($first, $second);
        $this->assertEqualsCanonicalizing(['First quote', 'Second quote'], [...$first, ...$second]);
        $this->assertContains('Next week only', $later);
        $this->assertNotContains('Next week only', $first);
    }

    public function test_a_missing_language_falls_back_to_english_and_text_is_escaped(): void
    {
        DayText::query()->create(['kind' => 'info', 'texts' => ['en' => '<script>alert(1)</script> Hello']]);
        RequestMemo::flush();

        $this->withSession(['locale' => 'fa'])->get('/')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Hello', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_hidden_texts_and_the_switched_off_default_leave_the_line_empty(): void
    {
        DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'Hidden one'], 'active' => false]);
        DayTexts::setShowDefault(false);

        $this->assertSame([], DayTexts::today(null, 'en'));
        $this->get('/')->assertOk()->assertSee('data-time-bar', false)->assertDontSee('data-marquee', false)->assertDontSee('Hidden one');

        Livewire::actingAs($this->superadmin())->test(DayTextsPage::class)
            ->assertSet('showDefault', false)
            ->set('showDefault', true);
        RequestMemo::flush();
        $this->assertSame('motto', DayTexts::today(null, 'en')[0]['kind']);
    }

    public function test_the_form_rejects_empty_texts_bad_ranges_and_unsafe_links(): void
    {
        Livewire::actingAs($this->superadmin())->test(DayTextsPage::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors('texts')
            ->set('texts.ar', 'نص')
            ->set('startsOn', '2026-11-10')
            ->set('endsOn', '2026-11-01')
            ->call('save')
            ->assertHasErrors('endsOn')
            ->set('endsOn', null)
            ->set('link', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('link')
            ->set('kind', 'advert')
            ->call('save')
            ->assertHasErrors('kind');

        $this->assertSame(0, DayText::query()->count());
    }

    public function test_superadmin_edits_reorders_hides_and_deletes(): void
    {
        $a = DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'A'], 'sort_order' => 0]);
        $b = DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'B'], 'sort_order' => 1]);

        Livewire::actingAs($this->superadmin())->test(DayTextsPage::class)
            ->call('move', $b->uuid, -1)
            ->call('edit', $a->uuid)
            ->assertSet('texts.en', 'A')
            ->set('texts.zh_CN', '甲')
            ->call('save')
            ->call('toggle', $b->uuid)
            ->call('delete', $a->uuid)
            ->assertHasNoErrors();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertFalse($b->fresh()->active);
        $this->assertNull($a->fresh());
    }

    public function test_an_ordinary_person_cannot_call_the_page_actions(): void
    {
        $text = DayText::query()->create(['kind' => 'quote', 'texts' => ['en' => 'Kept']]);

        Livewire::actingAs($this->person())->test(DayTextsPage::class)->assertForbidden();
        $this->assertNotNull($text->fresh());
    }

    public function test_pages_still_load_before_the_table_exists(): void
    {
        Schema::drop('day_texts');
        DayTexts::forget();

        $this->get('/')->assertOk()->assertSee(__('time_bar.default_text', [], 'en'));
    }

    public function test_the_superadmin_can_reach_the_editor_from_the_bar(): void
    {
        $this->actingAs($this->superadmin())->get(route('dashboard'))->assertSee('data-time-bar-edit', false);
        $this->actingAs($this->person())->get(route('dashboard'))->assertDontSee('data-time-bar-edit', false);
        $this->get('/')->assertDontSee('data-time-bar-edit', false);
    }

    public function test_navigation_lists_the_page_for_the_superadmin_only(): void
    {
        $this->actingAs($this->superadmin())->get(route('dashboard'))->assertSee(route('platform.day-texts'), false);
        $this->actingAs($this->person())->get(route('dashboard'))->assertDontSee(route('platform.day-texts'), false);
    }

    private function person(): User
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tehran']);
        Actor::factory()->for($user)->create();

        return $user;
    }

    private function superadmin(): User
    {
        $user = $this->person();
        PlatformAccessGrant::factory()->for($user)->create(['role' => PlatformRole::Superadmin]);

        return $user;
    }
}
