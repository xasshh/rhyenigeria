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

if (! function_exists('site_term')) {
    /**
     * Site-specific wording: site_term('canvasser') → "canvasser" on RHYE, "polling unit agent" on US27.
     * A capitalised key gives a capitalised result: site_term('Canvassers') → "Polling unit agents".
     */
    function site_term(string $word): string
    {
        $value = (string) config('site.'.mb_strtolower($word), $word);

        return ctype_upper($word[0]) ? ucfirst($value) : $value;
    }
}

if (! function_exists('site_state_code')) {
    /** The one state this site covers (e.g. "KD"), or null for a nationwide site. */
    function site_state_code(): ?string
    {
        return config('site.state_code') ? strtoupper((string) config('site.state_code')) : null;
    }
}

if (! function_exists('site_feature')) {
    /** Whether this site uses an optional feature (config/site.php `features`). On unless switched off. */
    function site_feature(string $feature): bool
    {
        return (bool) config("site.features.{$feature}", true);
    }
}
