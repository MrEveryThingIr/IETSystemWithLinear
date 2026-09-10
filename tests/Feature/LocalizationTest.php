<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_browser_language_negotiation_renders_arabic_with_rtl_direction(): void
    {
        $response = $this->withHeader('Accept-Language', 'ar,en;q=0.8')->get(route('login'));

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول');
    }

    public function test_guest_can_switch_locale_and_the_session_preference_wins_over_browser_language(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'ar');

        $this->withHeader('Accept-Language', 'en')
            ->get(route('login'))
            ->assertHeader('Content-Language', 'ar')
            ->assertSee('تسجيل الدخول');
    }

    public function test_authenticated_locale_preference_is_persisted_and_overrides_the_session(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'ar');

        $this->assertSame('ar', $user->refresh()->locale);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertHeader('Content-Language', 'ar')
            ->assertSee('لوحة التحكم');
    }

    public function test_unsupported_locale_is_rejected_without_changing_the_preference(): void
    {
        $this->withSession(['locale' => 'en'])
            ->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'not-supported'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'en');
    }

    public function test_an_additional_configured_locale_works_without_routing_or_schema_changes(): void
    {
        config()->set('localization.locales.zh_CN', [
            'name' => 'Chinese',
            'native_name' => '简体中文',
            'direction' => 'ltr',
        ]);

        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'zh_CN'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'zh_CN');

        $this->get(route('login'))
            ->assertHeader('Content-Language', 'zh-CN')
            ->assertSee('dir="ltr"', false)
            ->assertSee('Log in');
    }

    public function test_arabic_ui_catalog_has_the_same_keys_as_english(): void
    {
        $englishKeys = array_keys(Arr::dot(require lang_path('en/ui.php')));
        $arabicKeys = array_keys(Arr::dot(require lang_path('ar/ui.php')));

        sort($englishKeys);
        sort($arabicKeys);

        $this->assertSame($englishKeys, $arabicKeys);
    }

    public function test_user_locale_is_used_for_framework_notifications(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->assertSame('ar', $user->preferredLocale());

        app()->setLocale($user->preferredLocale());

        $message = (new VerifyEmail)->toMail($user);

        $this->assertSame('تحقق من عنوان بريدك الإلكتروني', $message->subject);
        $this->assertContains('يرجى النقر على الزر أدناه للتحقق من عنوان بريدك الإلكتروني.', $message->introLines);
    }
}
