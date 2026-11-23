<?php

namespace App\Enums;

/** What kind of incident a member or agent reports to the command centre. */
enum IncidentCategory: string
{
    case Violence = 'violence';
    case BallotSnatching = 'ballot_snatching';
    case VoteBuying = 'vote_buying';
    case Intimidation = 'intimidation';
    case Materials = 'materials';
    case Bvas = 'bvas';
    case LateOpening = 'late_opening';
    case Officials = 'officials';
    case NoSecurity = 'no_security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Violence => 'Violence or threats',
            self::BallotSnatching => 'Ballot box snatching',
            self::VoteBuying => 'Vote buying',
            self::Intimidation => 'Voters intimidated or turned away',
            self::Materials => 'Missing or late election materials',
            self::Bvas => 'BVAS or card reader problem',
            self::LateOpening => 'Polling unit opened late',
            self::Officials => 'Misconduct by officials',
            self::NoSecurity => 'No security personnel',
            self::Other => 'Something else',
        };
    }
}
