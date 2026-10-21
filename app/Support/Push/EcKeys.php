<?php

namespace App\Support\Push;

use OpenSSLAsymmetricKey;
use RuntimeException;

/** P-256 key helpers built on OpenSSL alone (no GMP needed on the host). */
final class EcKeys
{
    /** DER prefix of a P-256 SubjectPublicKeyInfo holding an uncompressed point. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** @param array<string, mixed>|false $details from openssl_pkey_get_details() */
    public static function rawPublic(array|false $details): string
    {
        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Not an EC key.');
        }

        return "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw): OpenSSLAsymmetricKey
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            throw new RuntimeException('Invalid P-256 public key.');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::SPKI_PREFIX).$raw), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid P-256 public key.');
        }

        return $key;
    }

    public static function privatePemFromRaw(string $d, string $publicRaw): string
    {
        // ECPrivateKey (RFC 5915) with the P-256 OID and the public point.
        $der = "\x30\x77\x02\x01\x01\x04\x20".str_pad($d, 32, "\0", STR_PAD_LEFT)
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".$publicRaw;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    /** DER ECDSA signature → raw 64-byte r||s (JWS ES256). */
    public static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7F) : 0);
        $read = function () use ($der, &$offset): string {
            $offset++; // INTEGER tag
            $length = ord($der[$offset++]);
            $value = substr($der, $offset, $length);
            $offset += $length;

            return str_pad(ltrim($value, "\0"), 32, "\0", STR_PAD_LEFT);
        };

        return $read().$read();
    }
}
