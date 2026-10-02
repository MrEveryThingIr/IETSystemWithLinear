<?php

namespace Tests\Feature\Businesses;

use App\Models\BusinessListingMedia;
use App\Models\BusinessPriceVersion;
use App\Models\SpaceContent;
use App\Models\User;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use App\Services\Contacts\BusinessContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessTraditionalListingWorkflowTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_real_estate_editor_accepts_persian_numbers_and_keeps_private_office_data_out_of_customer_preview(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Safdar Real Estate Office',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $client = app(BusinessContactResolver::class)->resolve(
            $business,
            'Mr. Ahmad',
            '09121234567',
            'walk_in',
        );

        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'property',
            ['title' => 'Initial property draft'],
            visibility: 'private',
        );

        $this->actingAs($owner)
            ->put(route('businesses.catalog.listings.update', [$business, $listing]), [
                'business_contact_id' => $client->id,
                'title' => 'ویلای احمد در ولیعصر',
                'short_description' => 'ویلای مسکونی آماده فروش',
                'description' => 'ملک مناسب خانواده و آماده بازدید است.',
                'visibility' => 'public',
                'availability_status' => 'available',
                'simple_office_mode' => '1',
                'transaction_mode' => 'sale',
                'property_class' => 'residential',
                'property_subtype' => 'villa',
                'exact_address' => 'تهران، ولیعصر، پلاک ۱۲۳ — اطلاعات خصوصی',
                'public_area' => 'ولیعصر',
                'land_area' => '۲۴۰٫۵۰',
                'construction_area' => '۱۸۰',
                'width' => '۸٫۵',
                'length' => '۳۰',
                'frontage_count' => '۲',
                'built_year' => '۱۴۰۲',
                'built_year_calendar' => 'jalali',
                'building_age_years' => '۳',
                'building_condition' => 'excellent',
                'bedrooms' => '۴',
                'floor_number' => '۲',
                'total_floors' => '۴',
                'has_elevator' => '1',
                'has_parking' => '1',
                'has_storage' => '1',
                'storage_area' => '۸٫۲۵',
                'has_balcony' => '1',
                'balcony_area' => '۱۲',
                'utilities_text' => 'آب، برق، گاز',
                'facilities_text' => 'آسانسور، پارکینگ، انباری',
                'public_notes' => 'بازدید با هماهنگی دفتر.',
                'private_notes' => 'مالک فقط عصرها پاسخ می‌دهد — خصوصی',
            ])
            ->assertRedirect(route('businesses.catalog.listings.edit', [$business, $listing]));

        $listing = $listing->fresh(['businessContact', 'currentVersion.propertyDetails']);
        $details = $listing->currentVersion->propertyDetails;

        $this->assertSame($client->id, $listing->business_contact_id);
        $this->assertTrue($listing->simple_office_mode);
        $this->assertSame('240.50', $details->land_area);
        $this->assertSame('180.00', $details->construction_area);
        $this->assertSame('8.50', $details->width);
        $this->assertSame(1402, $details->built_year);
        $this->assertSame(['آب', 'برق', 'گاز'], $details->utilities);
        $this->assertSame(['آسانسور', 'پارکینگ', 'انباری'], $details->facilities);

        $this->actingAs($owner)
            ->get(route('businesses.catalog.listings.preview', [$business, $listing]))
            ->assertOk()
            ->assertSee('ویلای احمد در ولیعصر')
            ->assertSee('ولیعصر')
            ->assertSee('بازدید با هماهنگی دفتر.')
            ->assertDontSee('Mr. Ahmad')
            ->assertDontSee('پلاک ۱۲۳')
            ->assertDontSee('مالک فقط عصرها پاسخ می‌دهد');
    }

    public function test_listing_media_price_and_presentation_publish_through_existing_asset_and_content_kernels(): void
    {
        Storage::fake('local');

        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Media Real Estate',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'property',
            [
                'title' => 'Apartment with media',
                'short_description' => 'Public property presentation',
            ],
            visibility: 'public',
        );

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.media.store', [$business, $listing]), [
                'media' => UploadedFile::fake()->image('front.jpg', 900, 600),
                'rights_status' => 'owned',
                'caption' => 'Front view',
                'visibility' => 'public',
                'cover' => '1',
            ])
            ->assertRedirect();

        $media = BusinessListingMedia::query()->with('asset')->sole();

        $this->assertSame('cover', $media->role);
        $this->assertSame('public', $media->visibility);
        $this->assertSame('owned', $media->asset->rights_status);

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.prices.store', [$business, $listing]), [
                'unit_code' => 'USD',
                'price_type' => 'asking_sale',
                'amount' => '۱۲۳۴٫۵۰',
                'basis' => 'total',
                'visibility' => 'public',
                'reason' => 'Initial asking price',
            ])
            ->assertRedirect();

        $price = BusinessPriceVersion::query()->with('monetaryUnit')->sole();
        $this->assertSame(123450, $price->amount_minor);
        $this->assertSame('USD', $price->monetaryUnit->code);

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.publish', [$business, $listing]))
            ->assertRedirect(route('businesses.catalog.listings.edit', [$business, $listing]));

        $listing = $listing->fresh(['currentVersion.presentationContent', 'publishedVersion']);
        $version = $listing->currentVersion;
        $content = $version->presentationContent;

        $this->assertSame('active', $listing->status);
        $this->assertSame($version->id, $listing->published_version_id);
        $this->assertNotNull($version->published_at);
        $this->assertInstanceOf(SpaceContent::class, $content);
        $this->assertSame('published', $content->status);

        $activeRevision = $content->activeRevisionRecord();
        $this->assertNotNull($activeRevision);
        $this->assertTrue($activeRevision->hasVerifiableManifest());
        $this->assertSame([$media->asset_id], $activeRevision->assets()->pluck('assets.id')->all());
        $this->assertStringContainsString(
            'Asking Sale: 1234.50 USD',
            (string) $activeRevision->payload['price_summary'],
        );

        $this->actingAs($owner)
            ->get(route('businesses.catalog.listings.edit', [$business, $listing]))
            ->assertOk()
            ->assertSee('Open advanced presentation editor')
            ->assertDontSee('Content Studio');
    }

    public function test_editing_a_published_listing_creates_a_new_working_version_and_preserves_published_history(): void
    {
        Storage::fake('local');

        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Versioned Property Office',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'property',
            ['title' => 'Original published property'],
            visibility: 'public',
        );

        $this->actingAs($owner)
            ->put(route('businesses.catalog.listings.update', [$business, $listing]), [
                'title' => 'Original published property',
                'visibility' => 'public',
                'availability_status' => 'available',
                'simple_office_mode' => '1',
                'transaction_mode' => 'sale',
                'property_class' => 'residential',
                'public_area' => 'Original neighborhood',
                'construction_area' => '۱۰۰',
                'public_notes' => 'Original public note',
                'private_notes' => 'Original private note',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.media.store', [$business, $listing]), [
                'media' => UploadedFile::fake()->image('original.jpg'),
                'rights_status' => 'owned',
                'caption' => 'Original image',
                'visibility' => 'public',
                'cover' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.publish', [$business, $listing]))
            ->assertRedirect();

        $published = $listing->fresh(['publishedVersion.propertyDetails', 'publishedVersion.media'])
            ->publishedVersion;

        $this->assertSame(1, $published->version_number);
        $this->assertNotNull($published->published_at);
        $this->assertSame(1, $published->media->count());

        $this->actingAs($owner)
            ->put(route('businesses.catalog.listings.update', [$business, $listing]), [
                'title' => 'Revised working property',
                'visibility' => 'public',
                'availability_status' => 'reserved',
                'simple_office_mode' => '1',
                'transaction_mode' => 'sale',
                'property_class' => 'residential',
                'public_area' => 'Revised neighborhood',
                'construction_area' => '۱۱۰',
                'public_notes' => 'Revised public note',
                'private_notes' => 'Revised private note',
            ])
            ->assertRedirect();

        $listing = $listing->fresh([
            'currentVersion.propertyDetails',
            'currentVersion.media',
            'publishedVersion.propertyDetails',
            'publishedVersion.media',
        ]);

        $working = $listing->currentVersion;
        $stillPublished = $listing->publishedVersion;

        $this->assertSame(2, $working->version_number);
        $this->assertNull($working->published_at);
        $this->assertNull($working->presentation_content_id);
        $this->assertSame('Revised working property', $working->title);
        $this->assertSame('110.00', $working->propertyDetails->construction_area);
        $this->assertSame(1, $working->media->count());

        $this->assertSame($published->id, $stillPublished->id);
        $this->assertSame('Original published property', $stillPublished->title);
        $this->assertSame('100.00', $stillPublished->propertyDetails->construction_area);
        $this->assertSame('Original public note', $stillPublished->propertyDetails->public_notes);
        $this->assertSame(1, $stillPublished->media->count());
    }

    public function test_public_listing_media_with_unknown_rights_blocks_publication(): void
    {
        Storage::fake('local');

        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Rights Checked Business',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'property',
            ['title' => 'Rights checked property'],
            visibility: 'public',
        );

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.media.store', [$business, $listing]), [
                'media' => UploadedFile::fake()->image('unknown.jpg'),
                'rights_status' => 'unknown',
                'visibility' => 'public',
                'cover' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.publish', [$business, $listing]))
            ->assertStatus(422);

        $this->assertNull($listing->fresh()->published_version_id);
        $this->assertNull($listing->currentVersion()->firstOrFail()->published_at);
    }

    public function test_business_listing_translation_contract_has_locale_parity(): void
    {
        $english = Arr::dot(require lang_path('en/business_listing.php'));

        foreach (['fa', 'ar', 'zh_CN'] as $locale) {
            $localized = Arr::dot(require lang_path($locale.'/business_listing.php'));

            foreach (array_keys($english) as $key) {
                $this->assertArrayHasKey($key, $localized, $locale.' is missing business_listing key '.$key);
            }
        }
    }

    private function userWithActor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->actor()->create();

        return $user->refresh();
    }
}
