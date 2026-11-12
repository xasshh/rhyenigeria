<?php

namespace App\Enums;

enum CanvasserRole: string
{
    case DoorToDoor = 'door_to_door';
    case SocialMedia = 'social_media';
    case EventMobilisation = 'event_mobilisation';
    case DrivingLogistics = 'driving_logistics';
    case WomensGroups = 'womens_groups';
    case YouthGroups = 'youth_groups';
    case ReligiousCommunityLeaders = 'religious_community_leaders';
    case PollingUnitAgent = 'polling_unit_agent';

    public function label(): string
    {
        return match ($this) {
            self::DoorToDoor => 'Door-to-door canvassing',
            self::SocialMedia => 'Social media',
            self::EventMobilisation => 'Event mobilisation',
            self::DrivingLogistics => 'Driving & logistics',
            self::WomensGroups => "Women's groups",
            self::YouthGroups => 'Youth groups',
            self::ReligiousCommunityLeaders => 'Religious & community leaders',
            self::PollingUnitAgent => 'Polling unit agent',
        };
    }
}
