<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The server's identity for Web Push (VAPID, P-256). Taken from
 * VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY when set, otherwise created once and
 * kept in storage (shared between releases on the host), so the host needs
 * no manual setup. Changing keys makes browsers subscribe again.
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
            return self::$keys = ['public' => $public, 'private_pem' => self::privatePem($private)];
        }

        $path = storage_path('app/private/webpush-vapid.json');
        if (is_file($path)) {
            $stored = json_decode((string) file_get_contents($path), true);
            if (is_array($stored) && is_string($stored['public'] ?? null) && is_string($stored['private_pem'] ?? null)) {
                return self::$keys = ['public' => $stored['public'], 'private_pem' => $stored['private_pem']];
            }
        }

        $generated = self::generate();
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode($generated, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($path, 0600);

        return self::$keys = $generated;
    }

    public static function publicKey(): string
    {
        return self::get()['public'];
    }

    /** @return array{public: string, private_pem: string} */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('Could not create Web Push keys (OpenSSL EC support is required).');
        }
        $details = openssl_pkey_get_details($key);

        return ['public' => Base64Url::encode(EcKeys::rawPublic($details)), 'private_pem' => (string) $pem];
    }

    public static function forgetCached(): void
    {
        self::$keys = null;
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
