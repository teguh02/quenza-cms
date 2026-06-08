<?php
declare(strict_types=1);

namespace Quenza\Core\Support;

final class Str
{
    public static function slug(string $value, string $separator = '-'): string
    {
        $normalized = trim(mb_strtolower($value));
        $normalized = preg_replace('/[^\pL\pN]+/u', $separator, $normalized) ?? '';
        $normalized = trim($normalized, $separator);

        return $normalized !== '' ? $normalized : 'item';
    }

    public static function excerpt(string $value, int $limit = 180): string
    {
        $plainText = trim(strip_tags($value));

        if (mb_strlen($plainText) <= $limit) {
            return $plainText;
        }

        return rtrim(mb_substr($plainText, 0, $limit - 3)) . '...';
    }
}
