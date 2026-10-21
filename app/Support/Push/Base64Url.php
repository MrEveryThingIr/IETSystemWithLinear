<?php

namespace App\Support\Push;

final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        $value = strtr(trim($value), '-_', '+/');

        return (string) base64_decode($value.str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
