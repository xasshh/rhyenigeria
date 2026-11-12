<?php

namespace App\Enums;

enum AgeRange: string
{
    case From18To24 = '18-24';
    case From25To34 = '25-34';
    case From35To44 = '35-44';
    case From45To54 = '45-54';
    case From55 = '55+';

    public function label(): string
    {
        return str_replace('-', '–', $this->value);
    }
}
