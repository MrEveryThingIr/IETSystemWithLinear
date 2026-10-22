<?php

namespace Tests\Feature;

use App\Support\Push\VapidKeys;
use Illuminate\Support\Facades\File;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

class PushProvisioningTest extends TestCase
{
    private string $originalStorage;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->scratch = sys_get_temp_dir().'/iet-push-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->scratch);
        config(['webpush.public_key' => '', 'webpush.private_key' => '']);
        VapidKeys::forgetCached();
    }

    protected function tearDown(): void
    {
        VapidKeys::forgetCached();
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->scratch);
        parent::tearDown();
    }

    public function test_rendering_with_missing_keys_never_provisions_or_throws(): void
    {
        config(['webpush.openssl_config' => $this->scratch.'/missing.cnf']);
        $this->assertSame('', VapidKeys::availablePublicKey());
        $html = view('components.app.device-notifications', [
            'attributes' => new ComponentAttributeBag,
        ])->render();
        $this->assertStringContainsString('data-device-notifications', $html);
        $this->assertStringContainsString(__('notifications.device.state_unavailable'), $html);
        $this->assertDirectoryDoesNotExist($this->scratch);
    }

    public function test_configured_test_identity_is_read_without_generation(): void
    {
        $keys = json_decode(file_get_contents(base_path('tests/Fixtures/push/vapid.json')), true);
        config(['webpush.public_key' => $keys['public'], 'webpush.private_key' => $keys['private_pem'],
            'webpush.openssl_config' => $this->scratch.'/missing.cnf']);
        $this->assertSame($keys['public'], VapidKeys::availablePublicKey());
        $this->assertDirectoryDoesNotExist($this->scratch);
    }

    public function test_invalid_keys_are_unavailable_without_replacing_them(): void
    {
        config(['webpush.public_key' => 'invalid', 'webpush.private_key' => 'invalid']);
        $this->assertSame('', VapidKeys::availablePublicKey());
        $this->assertDirectoryDoesNotExist($this->scratch);
    }

    public function test_real_provisioning_preserves_the_identity_on_repeated_runs(): void
    {
        $this->artisan('push:provision')->assertSuccessful();
        $path = storage_path('app/private/webpush-vapid.json');
        $before = file_get_contents($path);
        VapidKeys::forgetCached();
        $public = VapidKeys::publicKey();
        $this->artisan('push:provision')->assertSuccessful();
        $this->assertSame($before, file_get_contents($path));
        $this->assertSame($public, VapidKeys::publicKey());
    }
}
