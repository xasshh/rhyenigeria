<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Time left until a moment, split into units, so countdowns render on the server before any JavaScript runs. */
final class Countdown
{
    /**
     * @return array{weeks: int, days: int, totalDays: int, hours: int, minutes: int, seconds: int, done: bool}
     */
    public static function parts(CarbonImmutable $target, ?CarbonImmutable $now = null): array
    {
        $left = max(0, $target->getTimestamp() - ($now ?? CarbonImmutable::now())->getTimestamp());
        $totalDays = intdiv($left, 86400);

        return [
            'weeks' => intdiv($totalDays, 7),
            'days' => $totalDays % 7,
            'totalDays' => $totalDays,
            'hours' => intdiv($left % 86400, 3600),
            'minutes' => intdiv($left % 3600, 60),
            'seconds' => $left % 60,
            'done' => $left === 0,
        ];
    }
}
