<?php

namespace App\Support;

final class Mask
{
    /** Show only the last four characters of an identifier such as a NIN or VIN. */
    public static function identifier(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'Not added';
        }

        return str_repeat('•', max(strlen($value) - 4, 4)).substr($value, -4);
    }
}
