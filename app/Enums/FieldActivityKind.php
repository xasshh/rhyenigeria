<?php

namespace App\Enums;

enum FieldActivityKind: string
{
    case DoorToDoor = 'door_to_door';
    case EventCheckIn = 'event_check_in';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::DoorToDoor => 'Door-to-door visit',
            self::EventCheckIn => 'Event check-in',
            self::Report => 'Report',
        };
    }
}
