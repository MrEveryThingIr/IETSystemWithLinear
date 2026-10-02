<?php

namespace Database\Seeders;

use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Business\AdoptRealEstatePortalIntoBusiness;
use App\Services\Business\BusinessCatalogService;
use App\Services\Contacts\BusinessContactResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealEstateBusinessDemoSeeder extends Seeder
{
    public const PORTAL_UUID = 'b3a02c74-4420-4ade-8cc4-6f016dcbe487';

    public const PUBLIC_TOKEN = 'GxtXQH9FNtGh63J2RyfzG0Hz0mu1OwaqvdXZObfwtEMQYmbk';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = $this->owner();

        $portal = PublicIntakePortal::query()
            ->where('uuid', self::PORTAL_UUID)
            ->orWhere('public_token', self::PUBLIC_TOKEN)
            ->first();

        if (! $portal instanceof PublicIntakePortal) {
            $portal = PublicIntakePortal::query()->create([
                'uuid' => self::PORTAL_UUID,
                'public_token' => self::PUBLIC_TOKEN,
                'type' => 'real_estate',
                'title' => 'Safdar Real Estate Office',
                'welcome_heading' => 'ثبت ملک یا درخواست ملک',
                'welcome_body' => 'اطلاعات اولیه را ثبت کنید؛ دفتر پس از بررسی با شما تماس می‌گیرد.',
                'success_message' => 'اطلاعات شما با موفقیت برای دفتر ارسال شد.',
                'locale' => 'fa',
                'is_active' => true,
            ]);
        }

        PublicIntakePortalGrant::query()->updateOrCreate(
            [
                'public_intake_portal_id' => $portal->id,
                'user_id' => $user->id,
            ],
            ['role' => 'manager'],
        );

        $business = app(AdoptRealEstatePortalIntoBusiness::class)->execute($portal, $user);

        if ($portal->realEstateCases()->exists()) {
            return;
        }

        $resolver = app(BusinessContactResolver::class);

        $ownerContact = $resolver->resolve(
            $business,
            'Mr. Ahmad',
            '09121230001',
            'real_estate_demo',
        );

        $buyerContact = $resolver->resolve(
            $business,
            'Mr. Reza',
            '09121230002',
            'real_estate_demo',
        );

        $offer = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $ownerContact->id,
            'reference_code' => 'RE-DEMOOFFER1',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Mr. Ahmad',
            'phone' => '09121230001',
            'public_area' => 'Demo neighborhood',
            'exact_address' => 'Private demo address',
            'property_class' => 'residential',
            'property_subtype' => 'villa',
            'land_area' => 240,
            'construction_area' => 180,
            'width' => 8,
            'length' => 30,
            'frontage_count' => 1,
            'building_age_years' => 12,
            'building_condition' => 'good',
            'bedrooms' => 4,
            'cabinet_type' => 'mdf',
            'heating_system' => 'package',
            'cooling_system' => 'split',
            'has_parking' => true,
            'parking_spaces' => 2,
            'car_capacity' => 2,
            'roof_finish' => 'waterproof_membrane',
            'roof_note' => 'Waterproofed two years ago',
            'roof_has_parapet' => true,
            'has_western_toilet' => true,
            'has_iranian_toilet' => true,
            'asking_price' => 8000000000,
            'price_unit' => 'toman',
            'notes' => 'Seeded acceptance scenario property.',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'demo-offer-preview'),
        ]);

        PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $buyerContact->id,
            'reference_code' => 'RE-DEMONEED01',
            'intent' => 'need',
            'transaction_mode' => 'sale',
            'contact_name' => 'Mr. Reza',
            'phone' => '09121230002',
            'public_area' => 'Demo neighborhood',
            'property_class' => 'residential',
            'land_area' => 250,
            'has_parking' => true,
            'price_unit' => 'toman',
            'notes' => 'Needs a residential property around 200–300 m² with parking.',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'demo-need-preview'),
        ]);

        app(BusinessCatalogService::class)->promoteRealEstateOffer(
            $business,
            $offer,
            $user->actor,
        );
    }

    private function owner(): User
    {
        $portal = PublicIntakePortal::query()
            ->where('uuid', self::PORTAL_UUID)
            ->orWhere('public_token', self::PUBLIC_TOKEN)
            ->first();

        $granted = $portal?->grants()
            ->with('user.actor')
            ->where('role', 'manager')
            ->get()
            ->first(fn ($grant): bool => $grant->user?->actor?->status === 'active');

        if ($granted?->user instanceof User) {
            return $granted->user;
        }

        $existing = User::query()
            ->with('actor')
            ->where('email', 'test@example.com')
            ->first();

        if ($existing instanceof User && $existing->actor !== null) {
            return $existing;
        }

        $user = User::query()
            ->with('actor')
            ->where('status', 'active')
            ->whereHas('actor', fn ($query) => $query->where('status', 'active'))
            ->first();

        if ($user instanceof User) {
            return $user;
        }

        $user = User::factory()->create([
            'username' => 'realestate-demo-owner',
            'email' => 'realestate-demo@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->actor()->create();

        return $user->refresh();
    }
}
