<?php

namespace App\Support;

final class ProductColors
{
    public const MAX = 8;

    /**
     * @param  mixed  $input
     * @return list<string>
     */
    public static function normalizeList(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $value) {
            if (! is_string($value)) {
                continue;
            }
            $hex = self::normalizeHex($value);
            if ($hex !== null) {
                $out[] = $hex;
            }
        }

        return array_values(array_unique($out));
    }

    public static function normalizeHex(string $input): ?string
    {
        $value = strtoupper(trim($input));
        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, '#')) {
            $value = '#'.$value;
        }

        if (preg_match('/^#([0-9A-F]{3})$/', $value, $m)) {
            $chars = str_split($m[1]);

            return '#'.$chars[0].$chars[0].$chars[1].$chars[1].$chars[2].$chars[2];
        }

        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        return null;
    }
}
