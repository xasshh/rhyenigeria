<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Coordinator = 'coordinator';
    case FieldAgent = 'field_agent';
}
