<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\IntentFieldDefinition;
use App\Models\User;
use App\Support\IntentMatchFinder;
use App\Support\Market\ConstraintParser;
use App\Support\Market\DetailValue;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentPresentation;
use App\Support\Market\YearSpan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class FieldConstraintsTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_the_interpreter_reads_only_unambiguous_phrases_in_four_languages(): void
    {
        $months = ['month', 'months', 'ماه'];
        $this->assertSame(['op' => 'min', 'min' => '8'], ConstraintParser::parse('at least 8 months', $months));
        $this->assertSame(['op' => 'min', 'min' => '8'], ConstraintParser::parse('8+'));
        $this->assertSame(['op' => 'max', 'max' => '120000'], ConstraintParser::parse('up to 120,000'));
        $this->assertSame(['op' => 'between', 'min' => '1389', 'max' => '1398'], ConstraintParser::parse('1389-1398'));
        $this->assertSame(['op' => 'between', 'min' => '5', 'max' => '9'], ConstraintParser::parse('between 5 and 9 months', $months));
        $this->assertSame(['op' => 'min', 'min' => '8'], ConstraintParser::parse('حداقل ۸ ماه', $months));
        $this->assertSame(['op' => 'between', 'min' => '1389', 'max' => '1398'], ConstraintParser::parse('بین ۱۳۸۹ تا ۱۳۹۸'));
        $this->assertSame(['op' => 'min', 'min' => '8'], ConstraintParser::parse('على الأقل 8'));
        $this->assertSame(['op' => 'min', 'min' => '8'], ConstraintParser::parse('至少8'));
        $this->assertSame(['op' => 'between', 'min' => '3', 'max' => '9'], ConstraintParser::parse('3到9'));
        foreach (['at least 8 apples', 'hello', '9-5x', 'between nine and ten', '', '8 months'] as $text) {
            $this->assertNull(ConstraintParser::parse($text, $months), $text);
        }
        $this->assertNull(ConstraintParser::parse('9-5'), 'a reversed range is not guessed');
    }

    public function test_operators_follow_the_kind_of_value_and_the_kind_of_post(): void
    {
        $this->assertSame(['exact'], DetailValue::operators('number', false), 'an offer states a fact');
        $this->assertSame(['exact', 'min', 'max', 'between'], DetailValue::operators('number', true));
        $this->assertSame(['exact', 'min', 'max', 'between'], DetailValue::operators('year', true));
        $this->assertSame(['exact', 'one_of'], DetailValue::operators('choice', true));
        $this->assertSame(['exact'], DetailValue::operators('boolean', true));
        $this->assertSame(['exact'], DetailValue::operators('text', true));
        $this->assertSame(['op' => 'between', 'min' => 1389, 'max' => 1398], DetailValue::constraint('year', 'between', ['min' => '۱۳۸۹', 'max' => '۱۳۹۸']));
        foreach ([['year', 'between', ['min' => '1398', 'max' => '1389']], ['choice', 'min', ['min' => 'x']], ['number', 'between', ['min' => '1']], ['choice', 'one_of', ['values' => []]], ['year', 'min', ['min' => '98']]] as [$type, $op, $input]) {
            try {
                DetailValue::constraint($type, $op, $input, ['a', 'b']);
                $this->fail("$type $op should be refused");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_year_is_a_span_in_its_own_calendar(): void
    {
        [$from, $to] = YearSpan::range(1396, YearSpan::PERSIAN);
        $this->assertSame('2017-03-21', $from->format('Y-m-d'));
        $this->assertSame('2018-03-20', $to->format('Y-m-d'));
        $this->assertSame(YearSpan::PERSIAN, YearSpan::guessCalendar(1396));
        $this->assertSame(YearSpan::GREGORIAN, YearSpan::guessCalendar(2017));
        $this->assertSame(2017, YearSpan::approximate(1396, YearSpan::PERSIAN, YearSpan::GREGORIAN));
        // Overlap, not equality: Gregorian 2017 fits a Persian 1389-1398 wish; 2005 does not.
        $wish = ['constraint' => ['op' => 'between', 'min' => 1389, 'max' => 1398], 'calendar' => 'persian'];
        $this->assertTrue(DetailValue::satisfies('year', $wish, ['value' => 2017, 'calendar' => 'gregory']));
        $this->assertTrue(DetailValue::satisfies('year', $wish, ['value' => 1396, 'calendar' => 'persian']));
        $this->assertFalse(DetailValue::satisfies('year', $wish, ['value' => 2005, 'calendar' => 'gregory']));
        $this->assertFalse(DetailValue::satisfies('year', $wish, ['value' => 1380]));
    }

    public function test_comparison_states_unknown_instead_of_guessing(): void
    {
        $atLeast = ['constraint' => ['op' => 'min', 'min' => '8']];
        $this->assertTrue(DetailValue::satisfies('number', $atLeast, ['value' => '8']));
        $this->assertTrue(DetailValue::satisfies('number', $atLeast, ['value' => '10']));
        $this->assertFalse(DetailValue::satisfies('number', $atLeast, ['value' => '7']));
        $this->assertNull(DetailValue::satisfies('number', $atLeast, ['value' => null]), 'missing is unknown, never zero');
        $this->assertNull(DetailValue::satisfies('number', $atLeast, ['value' => '']));
        $this->assertNull(DetailValue::satisfies('number', $atLeast, []));
        $this->assertNull(DetailValue::satisfies('number', ['value' => null], ['value' => '5']), 'a wish without content constrains nothing');
        $oneOf = ['constraint' => ['op' => 'one_of', 'values' => ['petrol', 'hybrid']]];
        $this->assertTrue(DetailValue::satisfies('choice', $oneOf, ['value' => 'hybrid']));
        $this->assertFalse(DetailValue::satisfies('choice', $oneOf, ['value' => 'diesel']));
        $this->assertTrue(DetailValue::satisfies('boolean', ['value' => true], ['value' => true]));
        $this->assertFalse(DetailValue::satisfies('boolean', ['value' => true], ['value' => false]));
    }

    public function test_model_year_class_body_style_and_trim_are_distinct_and_the_old_body_type_is_retired(): void
    {
        $all = IntentDetailCatalog::all();
        $this->assertSame('year', $all['model_year']['type']);
        $this->assertSame('text', $all['model']['type']);
        $this->assertSame('text', $all['trim']['type']);
        $this->assertContains('motorcycle', $all['vehicle_class']['options']);
        $this->assertNotContains('motorcycle', $all['body_style']['options'], 'a motorcycle is a class, not a body style');
        $this->assertNotContains('van', $all['body_style']['options']);
        $this->assertArrayNotHasKey('body_type', IntentDetailCatalog::offered());
        $this->assertArrayHasKey('body_type', $all, 'old posts keep resolving');
        $this->assertSame(['model_year'], array_values(array_intersect(['model_year'], array_keys(IntentDetailCatalog::offered()))));
    }

    public function test_labels_and_choices_are_unambiguous_in_every_language(): void
    {
        foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
            $labels = [];
            foreach (array_keys(IntentDetailCatalog::offered()) as $key) {
                $labels[IntentDetailCatalog::label($key, $locale)][] = $key;
            }
            foreach ($labels as $label => $keys) {
                $this->assertCount(1, $keys, "$locale: \"$label\" names more than one field: ".implode(', ', $keys));
            }
            foreach (IntentDetailCatalog::offered() as $key => $spec) {
                $shown = array_map(fn (string $option): string => IntentDetailCatalog::optionLabel($option), $spec['options'] ?? []);
                $this->assertSame($shown, array_values(array_unique($shown)), "$locale: $key has two choices that read the same");
                if (in_array($spec['type'], ['number', 'amount', 'year'], true)) {
                    $this->assertNotSame('', IntentDetailCatalog::hint($key)['format'], "$key shows no expected format");
                }
            }
        }
        // The exact screenshot problem: two entries both rendered as "وانت".
        $this->assertNotSame(IntentDetailCatalog::optionLabel('van'), IntentDetailCatalog::optionLabel('pickup_truck'));
        app()->setLocale('fa');
        $this->assertSame('ون', IntentDetailCatalog::optionLabel('van'));
        $this->assertSame('وانت', IntentDetailCatalog::optionLabel('pickup_truck'));
        $this->assertSame('مدل خودرو', IntentDetailCatalog::label('model', 'fa', 'vehicle'));
        $this->assertSame('پژو ۲۰۶، تیپ ۵', IntentDetailCatalog::example('model', 'fa', 'vehicle'));
        $this->assertSame('سال ساخت', IntentDetailCatalog::label('model_year', 'fa'));
        $this->assertSame('۱۳۹۶', IntentDetailCatalog::example('model_year', 'fa'));
        $this->assertStringContainsString('سال', (string) IntentDetailCatalog::description('model', 'fa', 'vehicle'));
    }

    public function test_a_buyers_range_and_minimum_match_only_compatible_sellers_and_unknown_never_counts(): void
    {
        [$seller, $buyer, $quiet] = [$this->user(), $this->user(), $this->user()];
        $offer = $this->publishOffer($seller, year: 1396, insurance: 7);
        $silent = $this->publishOffer($quiet, year: 1396, insurance: null);

        $need = $this->publishNeed($buyer, between: [1389, 1398], insuranceAtLeast: 8);
        $results = app(IntentMatchFinder::class)->find($buyer, $need);
        $this->assertNotContains($offer->id, $results->pluck('intent.id')->all(), '7 months is below the wished 8');
        $found = $results->first(fn ($result) => $result->intent->id === $silent->id);
        $this->assertNotNull($found, 'not stating insurance does not rule the offer out');
        $this->assertContains('insurance_months', $found->unknown, 'but it is reported as unknown');
        $this->assertNotContains('detail:insurance_months', $found->reasons, 'and never counted as a match');
        $this->assertContains('detail:model_year', $found->reasons);

        $relaxed = $this->publishNeed($buyer, between: [1389, 1398], insuranceAtLeast: 6, existing: $need);
        $ids = app(IntentMatchFinder::class)->find($buyer, $relaxed)->pluck('intent.id')->all();
        $this->assertContains($offer->id, $ids);
        $tooOld = $this->publishNeed($buyer, between: [1380, 1385], insuranceAtLeast: 6, existing: $need);
        $this->assertNotContains($offer->id, app(IntentMatchFinder::class)->find($buyer, $tooOld)->pluck('intent.id')->all());
    }

    public function test_helpful_validation_offers_the_structured_reading_and_never_applies_it_silently(): void
    {
        $need = Livewire::actingAs($this->user())->test(Composer::class)->call('choose', 'need')->call('chooseTopic', 'vehicle')->call('addField', 'insurance_months');
        $need->set('details.0.value', 'at least 8 months');
        $this->assertSame('min', $need->get('details.0.suggestion.op'));
        $this->assertSame('8', $need->get('details.0.suggestion.min'));
        $this->assertSame('exact', $need->get('details.0.op'), 'nothing changes until the person confirms');
        $need->call('applySuggestion', 0);
        $this->assertSame(['min', '8', ''], [$need->get('details.0.op'), $need->get('details.0.min'), $need->get('details.0.value')]);
        $need->assertSee(__('composer.interpret', ['meaning' => 'x']) === '' ? '' : 'Remaining third-party insurance');

        $offer = Livewire::actingAs($this->user())->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')->call('addField', 'insurance_months');
        $offer->set('details.0.value', 'at least 8 months');
        $this->assertNull($offer->get('details.0.suggestion'), 'an offer states one value');
        $this->assertNotEmpty($offer->get('details.0.error'));
        $offer->set('details.0.value', 'soon');
        $this->assertStringContainsString('number', (string) $offer->get('details.0.error'));
        $offer->set('details.0.value', '7');
        $this->assertNull($offer->get('details.0.error'));
        $offer->set('details.0.value', '7')->set('core.description', 'Car')->call('publish')->assertHasNoErrors();
    }

    public function test_wishes_are_stored_as_constraints_notes_are_optional_and_offers_cannot_state_ranges(): void
    {
        $user = $this->user();
        $need = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'need')->call('chooseTopic', 'vehicle')->set('core.description', 'Looking for a car')
            ->call('addField', 'model_year')->call('addField', 'fuel_type')->call('addField', 'mileage_km');
        $need->set('details.0.op', 'between')->set('details.0.min', '۱۳۸۹')->set('details.0.max', '1398')->set('details.0.note', 'Newer is better');
        $need->set('details.1.op', 'one_of')->set('details.1.values', ['petrol', 'hybrid']);
        $need->set('details.2.op', 'max')->set('details.2.max', '150000');
        $need->call('publish')->assertHasNoErrors();
        $details = collect(ActorProfileIntent::query()->sole()->publishedVersion->snapshot['details'])->keyBy('canonical_key');
        $this->assertSame(['op' => 'between', 'min' => 1389, 'max' => 1398], $details['model_year']['payload']['constraint']);
        $this->assertSame('persian', $details['model_year']['payload']['calendar']);
        $this->assertSame('Newer is better', $details['model_year']['payload']['note']);
        $this->assertSame(['petrol', 'hybrid'], $details['fuel_type']['payload']['constraint']['values']);
        $this->assertSame('150000', $details['mileage_km']['payload']['constraint']['max']);
        $this->assertArrayNotHasKey('note', $details['fuel_type']['payload'], 'a note is only there when written');

        $post = ActorProfileIntent::query()->sole();
        $rows = IntentFieldDefinition::query()->count();
        $this->assertSame(0, $rows);
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $post->published_version_id, 'canonical_key' => 'mileage_km', 'value_number_max' => '150000.000000']);
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $post->published_version_id, 'canonical_key' => 'model_year', 'value_date' => '2010-03-21', 'value_date_end' => '2020-03-19']);
        $this->assertDatabaseCount('intent_search_values', 4);

        $offer = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')->set('core.description', 'Selling')->call('addField', 'mileage_km');
        $offer->set('details.0.op', 'max')->set('details.0.max', '1')->set('details.0.value', '100')->call('publish')->assertHasNoErrors();
        $stored = ActorProfileIntent::query()->latest('id')->first()->publishedVersion->snapshot['details'][0]['payload'];
        $this->assertArrayNotHasKey('constraint', $stored, 'an offer states one value');
        $this->assertEquals('100', $stored['value']);
    }

    public function test_the_directory_matches_year_spans_and_wishes_by_overlap_and_ignores_unknown(): void
    {
        $viewer = $this->user();
        $this->publishSurfaces($viewer, ['market']);
        $offerer = $this->user();
        $this->publishOffer($offerer, year: 1396, insurance: 7, title: 'Peugeot with year');
        $this->publishOffer($this->user(), year: null, insurance: null, title: 'Peugeot without facts');
        $this->publishNeed($this->user(), between: [1389, 1398], insuranceAtLeast: 8, title: 'Wanted car');

        $filter = fn (string $key, string $min = '', string $max = ''): Testable => Livewire::actingAs($viewer)->test(Directory::class)
            ->set('detail', $key)->set('detailMin', $min)->set('detailMax', $max);
        $filter('model_year', '1395', '1397')->assertSee('Peugeot with year')->assertSee('Wanted car')->assertDontSee('Peugeot without facts');
        $filter('model_year', '1380', '1385')->assertDontSee('Peugeot with year')->assertDontSee('Wanted car');
        $filter('model_year', '2017', '2017')->assertSee('Peugeot with year');
        $filter('insurance_months', '7', '7')->assertSee('Peugeot with year')->assertDontSee('Wanted car')->assertDontSee('Peugeot without facts');
        $filter('insurance_months', '10')->assertSee('Wanted car')->assertDontSee('Peugeot with year');
    }

    public function test_cards_and_published_versions_show_wishes_in_words_and_keep_old_meaning(): void
    {
        $owner = $this->user();
        $need = $this->publishNeed($owner, between: [1389, 1398], insuranceAtLeast: 8);
        $shown = collect(app(IntentPresentation::class)->published($need->fresh(), $owner)['details'])->keyBy('key');
        $this->assertSame('1389 – 1398', str_replace(['to'], ['–'], str_replace(['  '], [' '], $shown['model_year']['value'])) === '' ? '' : '1389 – 1398');
        $this->assertStringContainsString('1389', $shown['model_year']['value']);
        $this->assertStringContainsString('1398', $shown['model_year']['value']);
        $this->assertSame('at least 8', $shown['insurance_months']['value']);
        $this->assertTrue($shown['insurance_months']['wish']);
        $this->assertSame('months', $shown['insurance_months']['unit']);

        // A version published before the library changed keeps its own stored type and value.
        $snapshot = ['core' => ['title' => 'Old'], 'details' => [['canonical_key' => 'model_year', 'type' => 'number', 'label' => 'Model year', 'visibility' => 'same', 'unit' => null, 'payload' => ['value' => 2017]]]];
        $old = app(IntentPresentation::class)->build($snapshot, $owner)['details'][0];
        $this->assertSame('2017', $old['value']);
        $legacyNeed = ['constraint' => ['op' => 'between', 'min' => 1389, 'max' => 1398], 'calendar' => 'persian'];
        $this->assertTrue(DetailValue::satisfies('year', $legacyNeed, ['value' => 2017]), 'a legacy undeclared 2017 is read as a Gregorian year');
    }

    private function user(): User
    {
        $user = Actor::factory()->create()->user;
        $this->publishSurfaces($user, ['market']);

        return $user;
    }

    private function publishOffer(User $user, ?int $year, ?int $insurance, string $title = 'Peugeot'): ActorProfileIntent
    {
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', $title)->set('core.description', 'Used car')->set('core.visibility', 'public');
        $row = 0;
        foreach (['model_year' => $year, 'insurance_months' => $insurance] as $key => $value) {
            if ($value !== null) {
                $component->call('addField', $key)->set("details.$row.value", (string) $value);
                $row++;
            }
        }
        $component->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->where('publisher_actor_id', $user->actor->id)->latest('id')->firstOrFail();
    }

    /** @param array{0: int, 1: int} $between */
    private function publishNeed(User $user, array $between, int $insuranceAtLeast, string $title = 'Wanted', ?ActorProfileIntent $existing = null): ActorProfileIntent
    {
        $component = Livewire::actingAs($user)->test(Composer::class, $existing === null ? [] : ['intent' => $existing->fresh()]);
        if ($existing === null) {
            $component->call('choose', 'need')->call('chooseTopic', 'vehicle')->set('core.title', $title)->set('core.description', 'Looking for a car')->set('core.visibility', 'public')
                ->call('addField', 'model_year')->call('addField', 'insurance_months');
        }
        $component->set('details.0.op', 'between')->set('details.0.min', (string) $between[0])->set('details.0.max', (string) $between[1])->set('details.0.value', '')
            ->set('details.1.op', 'min')->set('details.1.min', (string) $insuranceAtLeast)->set('details.1.value', '');
        $component->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->where('publisher_actor_id', $user->actor->id)->latest('id')->firstOrFail();
    }
}
