<?php

if (! function_exists('staff_route')) {
    /**
     * The coordinator and super-admin dashboards share their member, canvasser,
     * empowerment and post pages. This picks the right route prefix for the
     * signed-in user: staff_route('members.index') → admin.members.index.
     */
    function staff_route(string $name, mixed $parameters = []): string
    {
        $prefix = auth()->user()?->isSuperAdmin() ? 'admin.' : 'coordinator.';

        return route($prefix.$name, $parameters);
    }
}
