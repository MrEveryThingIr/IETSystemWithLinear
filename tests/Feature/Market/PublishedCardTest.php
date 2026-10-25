<?php

namespace Tests\Feature\Market;

use App\Actions\Market\PublishIntent;
use App\Livewire\Intents\Composer;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Support\Market\IntentPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class PublishedCardTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_a_vehicle_leads_with_brand_model_year_and_condition_and_only_shows_what_was_supplied(): void
    {
        $owner = $this->user();
        $post = $this->vehicle($owner);
        $html = $this->render($post, $owner);
        foreach (['Make', 'Model', 'Year of manufacture', 'Condition'] as $lead) {
            $this->assertStringContainsString($lead, $html);
        }
        $this->assertLessThan(strpos($html, 'Color'), strpos($html, 'Year of manufacture'), 'highlights come before other details');
        $this->assertStringContainsString('data-kind="offer"', $html);
        $this->assertStringContainsString('Vehicle', $html);
        $this->assertStringContainsString('For sale', $html);
        $this->assertStringContainsString('Used car, one owner', $html);
        $this->assertStringContainsString('Only the owner note', $html);
        foreach (['Floor area', 'Rooms', 'Deposit', 'Starts', 'Where'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, "$absent was never supplied");
        }
        $this->assertStringNotContainsString('data-facts', $html, 'no price, place or time was supplied');
    }

    public function test_density_changes_how_much_is_shown_not_what_it_means(): void
    {
        $owner = $this->user();
        $post = $this->vehicle($owner, price: true);
        $data = app(IntentPresentation::class)->forVersion($post->fresh()->load('publishedVersion'), $owner);
        $card = Blade::render('<x-market.intention-presentation :presentation="$p" density="card" />', ['p' => $data]);
        $full = Blade::render('<x-market.intention-presentation :presentation="$p" />', ['p' => $data]);
        $this->assertStringContainsString('line-clamp-3', $card);
        $this->assertStringNotContainsString('data-details', $card);
        $this->assertStringNotContainsString('data-notes', $card);
        $this->assertStringContainsString('data-details', $full);
        $this->assertStringContainsString('data-notes', $full);
        $this->assertStringContainsString('data-facts', $card);
        $this->assertStringContainsString('2,000,000,000 Toman', $card);
    }

    public function test_a_need_says_budget_and_preferred_location_while_an_offer_says_price_and_where(): void
    {
        $user = $this->user();
        $need = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'need')->call('chooseTopic', 'repair')->set('core.description', 'Leaking pipe')
            ->set('core.visibility', 'public')->call('addTerm', 'price')->set('price', '500000')->call('addTerm', 'place')->set('core.location_text', 'Valiasr');
        $need->call('publish')->assertHasNoErrors();
        $html = $this->render(ActorProfileIntent::query()->sole(), $user);
        $this->assertStringContainsString('Budget', $html);
        $this->assertStringContainsString('Preferred location', $html);
        $this->assertStringContainsString('data-kind="need"', $html);

        $offer = $this->vehicle($user, price: true);
        $this->assertStringNotContainsString('Budget', $this->render($offer, $user));
    }

    public function test_the_detail_page_offers_the_natural_next_action_to_each_viewer(): void
    {
        $owner = $this->user();
        $post = $this->vehicle($owner, visibility: 'public');
        $this->app['auth']->forgetGuards();
        $this->get(route('intents.show', $post))->assertOk()->assertSee(route('login'), false)->assertDontSee(route('intents.compose.edit', $post), false);
        $this->actingAs($this->user())->get(route('intents.show', $post))->assertOk()->assertSee(route('relationships.create', ['intent' => $post->uuid]), false)->assertDontSee(route('intents.compose.edit', $post), false);
        $this->actingAs($owner)->get(route('intents.show', $post))->assertOk()->assertSee(route('intents.compose.edit', $post), false)->assertSee('Published as version 1');
    }

    public function test_the_directory_card_uses_the_shared_presentation_and_keeps_legacy_currency(): void
    {
        $owner = $this->user();
        $viewer = $this->user();
        $this->vehicle($owner, visibility: 'authenticated');
        $legacy = ActorProfileIntent::factory()->create(['visibility' => 'authenticated', 'title' => 'Legacy dollars', 'cash_min' => '50.00', 'cash_max' => '50.00', 'currency_code' => 'USD', 'exchange_preference' => 'cash_only']);
        PublishIntent::class;
        app(PublishIntent::class)->execute($legacy->profile->actor->user, $legacy);
        $this->publishSurfaces($viewer, ['market']);
        Livewire::actingAs($viewer)->test(Directory::class)->assertSee('Peugeot')->assertSee('Used car, one owner')->assertSee('Legacy dollars')->assertSee('50 USD')
            ->assertSeeHtml('data-density="card"')->assertDontSee('INT-0');
    }

    private function user(): User
    {
        $user = Actor::factory()->create()->user;
        $this->publishSurfaces($user, ['market']);

        return $user;
    }

    private function vehicle(User $owner, bool $price = false, string $visibility = 'public'): ActorProfileIntent
    {
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', 'Peugeot 206')->set('core.description', 'Used car, one owner')->set('core.visibility', $visibility);
        $rows = ['brand' => 'Peugeot', 'model' => '206', 'model_year' => '1396', 'condition' => 'used', 'color' => 'White'];
        $index = 0;
        foreach ($rows as $key => $value) {
            $component->call('addField', $key)->set("details.$index.value", $value);
            $index++;
        }
        $component->set('details.2.note', 'Only the owner note');
        if ($price) {
            $component->call('addTerm', 'price')->set('priceMode', 'fixed')->set('price', '2,000,000,000');
        }
        $component->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->where('publisher_actor_id', $owner->actor->id)->latest('id')->firstOrFail();
    }

    private function render(ActorProfileIntent $post, User $viewer): string
    {
        $data = app(IntentPresentation::class)->published($post->fresh(), $viewer);

        return Blade::render('<x-market.intention-presentation :presentation="$p" />', ['p' => $data]);
    }
}
