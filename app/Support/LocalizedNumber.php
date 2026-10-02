<?php

namespace App\Support;

final class LocalizedNumber
{
    public static function digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function decimal(string $value): string
    {
        $value = self::digits(trim($value));

        return str_replace(['٬', '،', ' ', '٫'], ['', '', '', '.'], $value);
    }

    public static function integer(string $value): string
    {
        return str_replace(['.', ','], '', self::decimal($value));
    }
}
