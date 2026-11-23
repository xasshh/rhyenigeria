<?php

namespace App\Models;

use Database\Factories\VoiceCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One call on the campaign phone line. See the create_voice_calls_table migration. */
#[Fillable(['session_id', 'direction', 'status', 'contact_phone', 'contact_user_id', 'operator_id', 'operator_phone'])]
class VoiceCall extends Model
{
    /** @use HasFactory<VoiceCallFactory> */
    use HasFactory;

    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'cost' => 'decimal:4',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** Still going: ringing or talking. */
    public function isLive(): bool
    {
        return in_array($this->status, ['ringing', 'connected'], true);
    }

    /** Someone called the line and nobody spoke to them, and nobody has dealt with it yet. */
    public function needsCallBack(): bool
    {
        return $this->direction === self::INBOUND && in_array($this->status, ['missed', 'voicemail'], true) && $this->handled_at === null;
    }
}
