<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public const TRAIL_VIEWED = 'trail.viewed';

    public const SUBMISSIONS_EXPORTED = 'submissions.exported';

    public const MEMBERS_MAP_VIEWED = 'members.map_viewed';

    public const ADMIN_CREATED = 'admin.created';

    public const ADMIN_DEACTIVATED = 'admin.deactivated';

    public const ADMIN_REACTIVATED = 'admin.reactivated';

    public const COMMAND_CENTRE_OPENED = 'command_centre.opened';

    public const COMMAND_CONTACTS_SEARCHED = 'command_centre.contacts_searched';

    public const COMMAND_CALL_PLACED = 'command_centre.call_placed';

    /**
     * Record who did what, and when.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(Request $request, string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        $user = $request->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'created_at' => now(),
        ]);
    }
}
