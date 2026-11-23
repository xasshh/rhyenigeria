<?php

namespace App\Enums;

/** How urgent the reporter says it is. */
enum IncidentUrgency: string
{
    case Critical = 'critical';
    case Serious = 'serious';
    case Minor = 'minor';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Urgent: people are in danger now',
            self::Serious => 'Serious: needs action today',
            self::Minor => 'Minor: for the record',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Critical => 'Urgent',
            self::Serious => 'Serious',
            self::Minor => 'Minor',
        };
    }
}
