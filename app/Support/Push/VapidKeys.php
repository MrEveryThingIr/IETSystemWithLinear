<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The server's identity for Web Push (VAPID, P-256). Taken from
 * VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY when set, otherwise provisioned once and
 * kept in storage (shared between releases on the host) by push:provision.
 * Changing keys makes browsers subscribe again.
 */
final class VapidKeys
{
    /** @var array{public: string, private_pem: string}|null */
    private static ?array $keys = null;

    /** @return array{public: string, private_pem: string} public = base64url of the raw 65-byte point */
    public static function get(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        $public = (string) config('webpush.public_key');
        $private = (string) config('webpush.private_key');
        if ($public !== '' && $private !== '') {
            return self::$keys = self::validated(['public' => $public, 'private_pem' => self::privatePem($private)]);
        }

        $path = storage_path('app/private/webpush-vapid.json');
        if (is_file($path)) {
            $stored = json_decode((string) file_get_contents($path), true);
            if (is_array($stored) && is_string($stored['public'] ?? null) && is_string($stored['private_pem'] ?? null)) {
                return self::$keys = self::validated(['public' => $stored['public'], 'private_pem' => $stored['private_pem']]);
            }
        }

        throw new RuntimeException('Web Push is not provisioned. Configure VAPID keys or run php artisan push:provision.');
    }

    /** Provision explicitly, never during a request or template render. */
    public static function provision(): void
    {
        $path = storage_path('app/private/webpush-vapid.json');
        File::ensureDirectoryExists(dirname($path));
        $lock = fopen($path.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot lock Web Push provisioning storage.');
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock Web Push provisioning storage.');
            }
            if (is_file($path) || (config('webpush.public_key') && config('webpush.private_key'))) {
                self::forgetCached();
                self::get();

                return;
            }
            $generated = self::generate();
            $json = json_encode($generated, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            try {
                if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
                    throw new RuntimeException('Cannot persist Web Push keys.');
                }
                @chmod($temporary, 0600);
                if (! rename($temporary, $path)) {
                    throw new RuntimeException('Cannot publish Web Push keys.');
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
            self::$keys = $generated;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** An empty key disables the browser control without provisioning cryptography. */
    public static function availablePublicKey(): string
    {
        try {
            return self::publicKey();
        } catch (\Throwable $exception) {
            return '';
        }
    }

    public static function publicKey(): string
    {
        return self::get()['public'];
    }

    /** @return array{public: string, private_pem: string} */
    public static function generate(): array
    {
        $options = self::opensslOptions();
        $key = openssl_pkey_new($options);
        if ($key === false || ! openssl_pkey_export($key, $pem, null, $options)) {
            throw new RuntimeException('Could not create Web Push P-256 keys. Check OPENSSL_CONF and webpush.openssl_config for this PHP runtime.');
        }
        $details = openssl_pkey_get_details($key);

        return ['public' => Base64Url::encode(EcKeys::rawPublic($details)), 'private_pem' => (string) $pem];
    }

    public static function forgetCached(): void
    {
        self::$keys = null;
    }

    /** @return array{curve_name: string, private_key_type: int, config?: string} */
    public static function opensslOptions(): array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $config = (string) config('webpush.openssl_config', '');
        if ($config !== '') {
            $options['config'] = $config;
        }

        return $options;
    }

    /** @param array{public: string, private_pem: string} $keys
     * @return array{public: string, private_pem: string}
     */
    private static function validated(array $keys): array
    {
        $key = openssl_pkey_get_private($keys['private_pem']);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! is_array($details) || ($details['ec']['curve_name'] ?? '') !== 'prime256v1'
            || ! hash_equals(Base64Url::encode(EcKeys::rawPublic($details)), $keys['public'])) {
            throw new RuntimeException('Web Push requires a matching P-256 public/private key pair.');
        }

        return $keys;
    }

    /** Accepts a PEM, or the base64url raw 32-byte private scalar used by other tools. */
    private static function privatePem(string $private): string
    {
        if (str_contains($private, 'BEGIN')) {
            return $private;
        }

        return EcKeys::privatePemFromRaw(Base64Url::decode($private), Base64Url::decode((string) config('webpush.public_key')));
    }
}
