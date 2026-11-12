<?php

namespace App\Enums;

/**
 * Everyone who signs up on the website is a `Member`. Canvassing is not a
 * separate role: a canvasser is a member whose `member_profiles.canvasser_status`
 * is `approved`. They keep the same card, dashboard, state coordinator and
 * scoping as every other member, and approval (or a later withdrawal) only
 * switches the canvasser tools on or off. It never moves anyone between roles,
 * and it can never grant field-agent or coordinator rights.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Coordinator = 'coordinator';
    case FieldAgent = 'field_agent';
    case Member = 'member';
}
