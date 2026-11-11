<?php

use App\Enums\AreaLevel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Live tracking channels. Only super admins and coordinators can join, and a
| coordinator only for an area or agent inside their own scope. Field agents
| cannot join any tracking channel.
*/

Broadcast::channel('tracking.all', fn (User $user) => $user->isSuperAdmin());

Broadcast::channel('tracking.{level}.{id}', function (User $user, string $level, string $id) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    if (! $user->isCoordinator() || ! ctype_digit($id)) {
        return false;
    }

    if ($level === 'agent') {
        $agent = User::query()->fieldAgents()->find((int) $id);

        return $agent !== null && $user->canSee($agent);
    }

    $areaLevel = AreaLevel::tryFrom($level);

    return $areaLevel !== null && $user->areaCovers($areaLevel, (int) $id);
});
