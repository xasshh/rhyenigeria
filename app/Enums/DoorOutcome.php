<?php

namespace App\Enums;

/** How a door-to-door visit went, as the canvasser judged it. */
enum DoorOutcome: string
{
    case Supportive = 'supportive';
    case Undecided = 'undecided';
    case NotSupportive = 'not_supportive';
    case NotHome = 'not_home';

    public function label(): string
    {
        return match ($this) {
            self::Supportive => 'Supportive',
            self::Undecided => 'Undecided',
            self::NotSupportive => 'Not supportive',
            self::NotHome => 'Nobody home',
        };
    }
}
