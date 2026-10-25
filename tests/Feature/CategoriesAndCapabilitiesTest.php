<?php

namespace Tests\Feature;

use App\Actions\Capabilities\SaveCapability;
use App\Actions\Categories\CreatePrivateCategory;
use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Categories\ReviewCategorySuggestions;
use App\Livewire\Business\MemberCapabilities;
use App\Livewire\Categories\Picker;
use App\Livewire\Platform\CategorySuggestions;
use App\Livewire\Profile\Capabilities;
use App\Models\Actor;
use App\Models\BusinessMembership;
use App\Models\Capability;
use App\Models\Concept;
use App\Models\ConceptSuggestion;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\Services\Business\BusinessService;
use App\Support\Categories\CategoryCatalog;
use App\Support\Categories\LifeDomainCatalog;
use App\Support\Parties\Party;
use Database\Seeders\ProfessionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class CategoriesAndCapabilitiesTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private Actor $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($this->admin->user)->create(['role' => PlatformRole::Superadmin]);
    }

    private function slug(string $slug): int
    {
        return (int) DB::table('concepts')->where('slug', $slug)->value('id');
    }

    public function test_shared_tree_installs_once_in_every_language_and_respects_retired_categories(): void
    {
        $this->assertTrue(app(EnsureSharedCategories::class)->ensureCurrent());

        $catalog = app(CategoryCatalog::class);
        app()->setLocale('fa');
        $areas = $catalog->areas();
        $this->assertCount(16, $areas);
        $this->assertSame('مسکن و زندگی', $areas[0]['label']);

        $electrical = $this->slug('housing.electrical');
        foreach (['en' => 'Electrical installation & repair', 'fa' => 'برق‌کاری ساختمان', 'ar' => 'التمديدات والإصلاحات الكهربائية', 'zh_CN' => '电气安装与维修'] as $locale => $label) {
            $this->assertSame($label, $catalog->labels([$electrical], $locale)[$electrical]);
        }
        $this->assertSame(['skill', 'service'], DB::table('concept_kinds')->where('concept_id', $electrical)->orderBy('id')->pluck('kind')->all());
        $this->assertSame(['housing', 'housing.electrical'], array_map(
            fn (array $step): string => (string) DB::table('concepts')->where('id', $step['id'])->value('slug'),
            $catalog->path($electrical, null),
        ));

        // Running again adds nothing.
        $counts = fn (): array => [DB::table('concepts')->count(), DB::table('concept_labels')->count(), DB::table('concept_hierarchy_edges')->count(), DB::table('concept_kinds')->count()];
        $before = $counts();
        app(EnsureSharedCategories::class)->execute();
        $this->assertSame($before, $counts());

        // A category an administrator retired is not brought back.
        Concept::query()->findOrFail($electrical)->applyLifecycle(['status' => 'deprecated', 'merged_into_concept_id' => null]);
        app(EnsureSharedCategories::class)->execute();
        $this->assertSame('deprecated', Concept::query()->findOrFail($electrical)->status->value);
        $this->assertNotContains($electrical, array_column($catalog->children($this->slug('housing'), null), 'id'));
    }

    public function test_inventory_only_reads(): void
    {
        $this->seed(ProfessionSeeder::class);
        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (! preg_match('/^\s*(select|pragma)\b/i', $query->sql)) {
                $writes++;
            }
        });

        $this->artisan('iet:inventory')->expectsOutputToContain('skills.by_profession.electrician.maps_to: housing.electrical')->assertSuccessful();
        $this->artisan('iet:inventory --json')->assertSuccessful();
        $this->assertSame(0, $writes);
    }

    public function test_old_skills_are_copied_once_and_never_changed(): void
    {
        $this->seed(ProfessionSeeder::class);
        $worker = Actor::factory()->create();
        $owner = Actor::factory()->create();
        $electrician = (int) DB::table('professions')->where('code', 'electrician')->value('id');
        $odd = (int) DB::table('professions')->insertGetId(['code' => 'beekeeper', 'name' => 'Beekeeper', 'name_fa' => 'زنبوردار', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor_professions')->insert([
            ['actor_id' => $worker->id, 'profession_id' => $electrician, 'level' => 'expert', 'years_experience' => 12, 'is_primary' => true, 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now()],
            ['actor_id' => $worker->id, 'profession_id' => $odd, 'level' => 'beginner', 'years_experience' => null, 'is_primary' => false, 'visibility' => 'contacts', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $business = app(BusinessService::class)->create($owner, ['name' => 'Spark Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $membership = app(BusinessService::class)->addMember($business, $worker);
        DB::table('business_membership_profession')->insert(['business_membership_id' => $membership->id, 'profession_id' => $electrician, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        $legacyBefore = [DB::table('actor_professions')->get()->toArray(), DB::table('business_membership_profession')->get()->toArray()];

        $this->artisan('iet:taxonomy:sync --dry-run')->expectsOutputToContain('Would copy: 2 people skills, 1 business member skills')->assertSuccessful();
        $this->assertSame(0, Capability::query()->count());

        $this->artisan('iet:taxonomy:sync')->assertSuccessful();
        $this->artisan('iet:taxonomy:sync')->assertSuccessful();

        $this->assertSame(3, Capability::query()->count());
        $expert = Capability::query()->where('legacy_actor_profession_id', '!=', null)->where('level', 'expert')->sole();
        $this->assertSame($this->slug('housing.electrical'), (int) $expert->concept_id);
        $this->assertSame([12, true, 'public', (int) $worker->id], [$expert->years_experience, $expert->is_primary, $expert->visibility, (int) $expert->holder_actor_id]);

        // A skill missing from the shared tree gets its own shared category, labels kept.
        $bees = Capability::query()->where('level', 'beginner')->sole();
        $this->assertSame('legacy.profession.beekeeper', Concept::query()->findOrFail($bees->concept_id)->slug);
        $this->assertSame('زنبوردار', Concept::query()->findOrFail($bees->concept_id)->displayLabel('fa'));
        $this->assertSame('contacts', $bees->visibility);

        $member = Capability::query()->whereNotNull('legacy_membership_profession_id')->sole();
        $this->assertSame((int) $membership->id, (int) $member->business_membership_id);

        $this->assertEquals($legacyBefore, [DB::table('actor_professions')->get()->toArray(), DB::table('business_membership_profession')->get()->toArray()]);
    }

    public function test_private_categories_stay_with_their_owner(): void
    {
        $me = Actor::factory()->create();
        $stranger = Actor::factory()->create();
        $catalog = app(CategoryCatalog::class);
        $catalog->sharedVocabularyId();
        $tiling = $this->slug('housing.tiling');

        $mine = app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), $tiling, 'Pool tiling');
        $this->assertSame(['service', 'skill'], DB::table('concept_kinds')->where('concept_id', $mine->id)->pluck('kind')->sort()->values()->all());
        $this->assertSame($mine->id, app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), $tiling, 'pool  tiling')->id);

        $this->assertContains((int) $mine->id, array_column($catalog->children($tiling, Party::of($me)), 'id'));
        $this->assertNotContains((int) $mine->id, array_column($catalog->children($tiling, Party::of($stranger)), 'id'));
        $this->assertSame([], array_values(array_filter($catalog->search('pool', Party::of($stranger)), fn (array $item): bool => $item['id'] === (int) $mine->id)));
        $this->assertFalse($catalog->isUsableBy((int) $mine->id, Party::of($stranger)));
        // The shared parent does not reveal that a stranger has something under it.
        $this->assertFalse(collect($catalog->children($this->slug('housing'), Party::of($stranger)))->firstWhere('id', $tiling)['has_children']);

        $this->expectException(HttpException::class);
        app(CreatePrivateCategory::class)->execute($stranger->user, Party::of($me), $tiling, 'Sneaky');
    }

    public function test_suggestions_are_reviewed_and_everything_follows_the_shared_category(): void
    {
        $me = Actor::factory()->create();
        $other = Actor::factory()->create();
        app(CategoryCatalog::class)->sharedVocabularyId();
        $mine = app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), $this->slug('housing.tiling'), 'Pool tiling');
        $child = app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), (int) $mine->id, 'Mosaic pools');
        $capability = app(SaveCapability::class)->create($me->user, Party::of($me), ['concept_id' => $mine->id, 'visibility' => 'public', 'self_confidence_percent' => 85]);

        $review = app(ReviewCategorySuggestions::class);
        $suggestion = $review->suggest($me->user, $mine, 'Common in villas');
        $this->assertSame($suggestion->id, $review->suggest($me->user, $mine)->id);

        try {
            $review->approve($other->user, $suggestion);
            $this->fail('Only reviewers may approve.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Livewire::actingAs($other->user)->test(CategorySuggestions::class)->assertForbidden();

        Livewire::actingAs($this->admin->user)->test(CategorySuggestions::class)
            ->assertSee('Pool tiling')
            ->call('open', $suggestion->uuid)
            ->set('decision', 'approve')
            ->call('decide')
            ->assertHasNoErrors();

        $suggestion->refresh();
        $this->assertSame(ConceptSuggestion::APPROVED, $suggestion->status);
        $shared = Concept::query()->findOrFail($suggestion->result_concept_id);
        $catalog = app(CategoryCatalog::class);
        $this->assertTrue($catalog->isShared((int) $shared->id));
        $this->assertSame((int) $shared->id, (int) $capability->fresh()->concept_id);
        $this->assertSame('merged', $mine->fresh()->status->value);
        $this->assertSame($this->slug('housing.tiling'), $catalog->path((int) $shared->id, null)[1]['id']);
        // Everyone can find it now; my own subcategory moved with it.
        $this->assertContains((int) $shared->id, array_column($catalog->search('pool tiling', Party::of($other)), 'id'));
        $this->assertContains((int) $child->id, array_column($catalog->children((int) $shared->id, Party::of($me)), 'id'));

        // Reject leaves it private; merge points it to an existing category.
        $second = app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), $this->slug('food'), 'Saffron farming');
        $rejected = $review->suggest($me->user, $second);
        $review->reject($this->admin->user, $rejected, 'Too narrow');
        $this->assertSame('active', $second->fresh()->status->value);
        $third = app(CreatePrivateCategory::class)->execute($me->user, Party::of($me), $this->slug('food'), 'Growing crops');
        $review->merge($this->admin->user, $review->suggest($me->user, $third), $this->slug('food.farming'));
        $this->assertSame($this->slug('food.farming'), (int) $third->fresh()->merged_into_concept_id);
    }

    public function test_capability_rules(): void
    {
        $me = Actor::factory()->create();
        $owner = Actor::factory()->create();
        $stranger = Actor::factory()->create();
        app(CategoryCatalog::class)->sharedVocabularyId();
        $save = app(SaveCapability::class);
        $labour = $this->slug('housing.construction-labour');

        $capability = $save->create($me->user, Party::of($me), ['concept_id' => $labour, 'visibility' => 'members', 'self_confidence_percent' => 85, 'level' => 'advanced', 'years_experience' => 6, 'is_primary' => true]);
        $this->assertSame(85, $capability->self_confidence_percent);

        foreach ([['self_confidence_percent' => 101], ['visibility' => 'world'], ['level' => 'godlike']] as $bad) {
            try {
                $save->create($me->user, Party::of($me), [...['concept_id' => $this->slug('housing.painting'), 'visibility' => 'members'], ...$bad]);
                $this->fail('Invalid capability saved.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $save->create($me->user, Party::of($me), ['concept_id' => $labour, 'visibility' => 'members']);
            $this->fail('Duplicate saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('conceptId', $exception->errors());
        }
        try {
            $save->create($stranger->user, Party::of($me), ['concept_id' => $this->slug('housing.painting'), 'visibility' => 'members']);
            $this->fail('Acted for someone else.');
        } catch (HttpException) {
            $this->addToAssertionCount(1);
        }

        // A business manager maintains a member's skills in that business; outsiders cannot.
        $business = app(BusinessService::class)->create($owner, ['name' => 'Build Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $membership = app(BusinessService::class)->addMember($business, $me);
        $this->publishSurfaces($owner->user, ['business']);
        Livewire::actingAs($owner->user)->test(MemberCapabilities::class, ['membership' => $membership])
            ->set('conceptId', $this->slug('housing.plumbing'))
            ->assertHasNoErrors();
        $this->assertSame((int) $me->id, (int) Capability::query()->where('business_membership_id', $membership->id)->sole()->holder_actor_id);
        Livewire::actingAs($stranger->user)->test(MemberCapabilities::class, ['membership' => BusinessMembership::query()->findOrFail($membership->id)])
            ->assertForbidden();
        $this->assertSame(1, Capability::query()->where('business_membership_id', $membership->id)->count());
    }

    public function test_a_capability_has_exactly_one_holder(): void
    {
        $me = Actor::factory()->create();
        $this->expectException(LogicException::class);
        Capability::query()->create(['concept_id' => $this->slugAfterSync('housing'), 'created_by_actor_id' => $me->id]);
    }

    private function slugAfterSync(string $slug): int
    {
        app(EnsureSharedCategories::class)->ensureCurrent();

        return $this->slug($slug);
    }

    public function test_capabilities_page_and_picker(): void
    {
        $me = Actor::factory()->create();
        $other = Actor::factory()->create();
        $this->publishSurfaces($me->user, ['profile']);
        app(CategoryCatalog::class)->sharedVocabularyId();

        $this->actingAs($me->user)->get(route('profile.professions.index'))->assertRedirect(route('items.index').'#abilities');
        $this->actingAs($me->user)->get(route('items.index'))->assertOk()->assertSee(__('items.abilities.title'));
        $this->actingAs($me->user)->post(route('profile.professions.store'), ['profession_id' => 1])->assertRedirect(route('profile.professions.index'));
        $this->assertSame(0, DB::table('actor_professions')->count());

        $picker = Livewire::actingAs($me->user)->test(Picker::class, ['owner' => 'actor:'.$me->id, 'kinds' => ['skill']])
            ->assertSeeHtml('data-category-areas')
            ->set('search', 'plumb')
            ->assertSee('Plumbing')
            ->call('browse', $this->slug('housing'))
            ->set('newCategory', 'Solar water heaters')
            ->set('suggest', true)
            ->call('addCategory')
            ->assertSet('open', false);
        $created = (int) $picker->get('value');
        $this->assertSame('Solar water heaters', Concept::query()->findOrFail($created)->displayLabel());
        $this->assertSame(1, ConceptSuggestion::query()->where('concept_id', $created)->count());

        // A forged owner is refused.
        Livewire::actingAs($me->user)->test(Picker::class, ['owner' => 'actor:'.$other->id])->assertForbidden();

        Livewire::actingAs($me->user)->test(Capabilities::class, ['embedded' => true])
            ->call('add')
            ->set('conceptId', $created)
            ->set('confidence', 85)
            ->set('level', 'advanced')
            ->set('yearsExperience', '7')
            ->set('visibility', 'public')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('capabilities.confidence_badge', ['percent' => 85]));
        $this->assertSame(7, Capability::query()->sole()->years_experience);
    }

    public function test_catalog_covers_every_old_skill(): void
    {
        $slugs = LifeDomainCatalog::slugs();
        foreach (LifeDomainCatalog::LEGACY_PROFESSIONS as $code => $slug) {
            $this->assertContains($slug, $slugs, $code);
        }
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }
}
