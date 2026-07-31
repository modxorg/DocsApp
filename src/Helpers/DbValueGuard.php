<?php

namespace MODXDocs\Helpers;

class DbValueGuard
{
    public const TERM = 100;
    public const PHONETIC_TERM = 100;
    public const LANGUAGE = 10;
    public const VERSION = 25;
    public const URL = 255;
    public const TITLE = 190;
    public const SEARCH_QUERY = 190;
    public const GIT_HASH = 190;
    public const NAME = 190;
    public const EMAIL = 190;
    public const MESSAGE = 500;
    public const TRANSLATION_URI = 255;

    public static function fits(?string $value, int $maxLength): bool
    {
        return $value !== null && $value !== '' && mb_strlen($value) <= $maxLength;
    }

    public static function truncate(?string $value, int $maxLength): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
