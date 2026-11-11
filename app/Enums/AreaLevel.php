<?php

namespace App\Enums;

enum AreaLevel: string
{
    case State = 'state';
    case Lga = 'lga';
    case Ward = 'ward';

    /** The foreign key column that holds an area of this level. */
    public function column(): string
    {
        return $this->value.'_id';
    }
}
