<?php

namespace App\Support;

/**
 * Nigerian mobile numbers, always stored as +234 followed by ten digits.
 * Accepts 0803 000 0000, 803 000 0000, 234803..., +234 803 ... and similar.
 */
final class NigerianPhone
{
    /** The +234 form, or null if this is not a Nigerian mobile number. */
    public static function normalise(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        $local = match (true) {
            strlen($digits) === 13 && str_starts_with($digits, '234') => substr($digits, 3),
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            strlen($digits) === 10 => $digits,
            default => null,
        };

        // Mobile numbers start 70, 80, 81, 90 or 91.
        if ($local === null || ! preg_match('/^[789][01]\d{8}$/', $local)) {
            return null;
        }

        return '+234'.$local;
    }

    /** +234 803 *** **67 */
    public static function mask(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }

        if (! preg_match('/^\+234(\d{3})\d{5}(\d{2})$/', $phone, $m)) {
            return str_repeat('*', max(strlen($phone) - 2, 0)).substr($phone, -2);
        }

        return "+234 {$m[1]} *** **{$m[2]}";
    }

    /** +234 803 123 4567 */
    public static function format(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }

        return preg_match('/^\+234(\d{3})(\d{3})(\d{4})$/', $phone, $m) ? "+234 {$m[1]} {$m[2]} {$m[3]}" : $phone;
    }
}
