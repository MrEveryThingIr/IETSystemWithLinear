<?php

namespace Tests\Feature\Market;

use App\Models\IntentFieldDefinition;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FieldLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_understands_localized_names_synonyms_and_digits(): void
    {
        $this->assertSame('mileage_km', IntentFieldLibrary::search('mileage')[0]);
        $this->assertContains('mileage_km', IntentFieldLibrary::search('odometer'));
        $this->assertContains('pay', IntentFieldLibrary::search('salary'));
        $this->assertContains('rooms', IntentFieldLibrary::search('bedrooms'));
        $this->assertSame([], IntentFieldLibrary::search('   '));
        $this->assertSame([], IntentFieldLibrary::search('zzzqqq'));

        app()->setLocale('fa');
        $this->assertSame('mileage_km', IntentFieldLibrary::search('کارکرد')[0]);
        $this->assertContains('floor_area', IntentFieldLibrary::search('متراژ'));
        $this->assertContains('mileage_km', IntentFieldLibrary::search('mileage'), 'English still works in a Persian interface');
        $this->assertContains('rooms', IntentFieldLibrary::search('اتاق خواب'));
        $this->assertSame(IntentFieldLibrary::normalize('كاركرد ۱۲'), IntentFieldLibrary::normalize('کارکرد 12'));

        app()->setLocale('ar');
        $this->assertContains('mileage_km', IntentFieldLibrary::search('كيلومترات'));
        app()->setLocale('zh_CN');
        $this->assertContains('mileage_km', IntentFieldLibrary::search('里程'));
    }

    public function test_library_is_grouped_by_domain_and_excludes_what_is_already_selected(): void
    {
        $groups = IntentFieldLibrary::grouped(['brand']);
        $this->assertArrayHasKey('vehicle', $groups);
        $this->assertArrayHasKey('hospitality', $groups);
        $this->assertNotContains('brand', $groups['general']);
        $this->assertContains('mileage_km', $groups['vehicle']);
        $this->assertSame(array_keys($groups), array_values(array_intersect(IntentDetailCatalog::DOMAINS, array_keys($groups))));
    }

    public function test_approved_definitions_add_keys_and_overlay_words_but_never_change_a_builtin_meaning(): void
    {
        $this->definition('loom_type', ['type' => 'choice', 'filter' => 'value_text', 'options' => ['hand', 'machine'], 'labels' => ['en' => 'Loom type', 'fa' => 'نوع دار', 'ar' => 'نوع النول', 'zh_CN' => '织机类型']]);
        $this->definition('brand', ['type' => 'number', 'filter' => 'value_number', 'labels' => ['en' => 'Make', 'fa' => 'برند', 'ar' => 'ماركة', 'zh_CN' => '品牌']]);

        $all = IntentDetailCatalog::all();
        $this->assertSame('choice', $all['loom_type']['type']);
        $this->assertSame('community', $all['loom_type']['source']);
        $this->assertSame('text', $all['brand']['type'], 'a built-in keeps its type whatever an overlay says');
        $this->assertSame('value_text', $all['brand']['filter']);
        $this->assertSame('Loom type', IntentDetailCatalog::label('loom_type', 'en'));
        $this->assertSame('نوع دار', IntentDetailCatalog::label('loom_type', 'fa'));
        $this->assertSame('Make', IntentDetailCatalog::label('brand', 'en'), 'words may be improved by review');
        $this->assertContains('loom_type', IntentFieldLibrary::search('loom'));
    }

    public function test_retired_fields_leave_the_library_but_stay_resolvable_for_old_posts(): void
    {
        $this->definition('old_thing', ['type' => 'text', 'filter' => 'value_text', 'labels' => ['en' => 'Old thing', 'fa' => 'قدیمی', 'ar' => 'قديم', 'zh_CN' => '旧']]);
        $definition = IntentFieldDefinition::query()->where('field_key', 'old_thing')->sole();
        $definition->update(['status' => 'retired', 'retired_at' => now()]);
        IntentDetailCatalog::bumpVersion();

        $this->assertArrayHasKey('old_thing', IntentDetailCatalog::all());
        $this->assertArrayNotHasKey('old_thing', IntentDetailCatalog::offered());
        $this->assertNotContains('old_thing', IntentFieldLibrary::search('old'));
        $this->assertSame('Old thing', IntentDetailCatalog::label('old_thing', 'en'));
    }

    public function test_published_definitions_are_immutable_and_never_deleted(): void
    {
        $this->definition('stable_key', ['type' => 'text', 'filter' => 'value_text', 'labels' => ['en' => 'Stable', 'fa' => 'ثابت', 'ar' => 'ثابت', 'zh_CN' => '稳定']]);
        $definition = IntentFieldDefinition::query()->sole();
        $this->expectException(LogicException::class);
        $definition->update(['type' => 'number']);
    }

    public function test_deleting_a_definition_is_refused(): void
    {
        $this->definition('keep_me', ['type' => 'text', 'filter' => 'value_text', 'labels' => ['en' => 'Keep', 'fa' => 'نگه', 'ar' => 'احتفظ', 'zh_CN' => '保留']]);
        $this->expectException(LogicException::class);
        IntentFieldDefinition::query()->sole()->delete();
    }

    /** @param array<string, mixed> $attributes */
    private function definition(string $key, array $attributes): IntentFieldDefinition
    {
        $definition = IntentFieldDefinition::query()->create(['field_key' => $key, 'version' => 1, 'status' => 'published', 'unit' => null, 'options' => [],
            'domains' => ['general'], 'descriptions' => [], 'aliases' => [], 'published_at' => now(), ...$attributes]);
        IntentDetailCatalog::bumpVersion();

        return $definition;
    }
}
