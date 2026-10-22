<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends one encrypted Web Push message (RFC 8291, aes128gcm) with a VAPID
 * signature (RFC 8292) using OpenSSL only. The browser's push service
 * (Google, Mozilla, Microsoft, Apple) delivers it to the device even when
 * the site is not open.
 */
final class WebPushSender
{
    /**
     * @param  array{title: string, body?: string, url?: string, tag?: string, important?: bool}  $message
     * @return string 'sent', 'gone' (subscription expired: remove it) or 'failed'
     */
    public function send(PushSubscription $subscription, array $message, int $ttl = 86400): string
    {
        try {
            $body = $this->encrypt(
                (string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                Base64Url::decode($subscription->public_key),
                Base64Url::decode($subscription->auth_token),
            );

            $response = Http::timeout(8)->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => $this->vapidHeader($subscription->endpoint),
                    'Content-Encoding' => 'aes128gcm',
                    'Content-Type' => 'application/octet-stream',
                    'TTL' => (string) $ttl,
                    'Urgency' => 'high',
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($subscription->endpoint);

            if (in_array($response->status(), [404, 410], true)) {
                return 'gone';
            }

            return $response->successful() ? 'sent' : 'failed';
        } catch (Throwable $exception) {
            report($exception);

            return 'failed';
        }
    }

    public function encrypt(string $payload, string $userPublic, string $userAuth): string
    {
        $local = openssl_pkey_new(VapidKeys::opensslOptions());
        if ($local === false) {
            throw new \RuntimeException('OpenSSL could not create an EC key.');
        }
        $localPublic = EcKeys::rawPublic(openssl_pkey_get_details($local));
        $shared = openssl_pkey_derive(EcKeys::publicKeyFromRaw($userPublic), $local, 32);
        if ($shared === false) {
            throw new \RuntimeException('OpenSSL could not derive the shared secret.');
        }

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$userPublic.$localPublic, $userAuth);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $tag = '';
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Web Push encryption failed.');
        }

        return $salt.pack('N', 4096).chr(65).$localPublic.$cipher.$tag;
    }

    private function vapidHeader(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $keys = VapidKeys::get();

        $header = Base64Url::encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = Base64Url::encode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => (string) config('webpush.subject'),
        ], JSON_UNESCAPED_SLASHES));
        $signature = '';
        openssl_sign($header.'.'.$claims, $signature, $keys['private_pem'], OPENSSL_ALGO_SHA256);

        return 'vapid t='.$header.'.'.$claims.'.'.Base64Url::encode(EcKeys::derToRaw($signature)).', k='.$keys['public'];
    }
}
