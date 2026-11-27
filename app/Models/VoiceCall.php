<?php

namespace App\Models;

use Database\Factories\VoiceCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One call on the campaign phone line. See the create_voice_calls_table migration. */
#[Fillable(['session_id', 'direction', 'channel', 'status', 'note', 'contact_phone', 'contact_user_id', 'operator_id', 'operator_phone'])]
class VoiceCall extends Model
{
    /** @use HasFactory<VoiceCallFactory> */
    use HasFactory;

    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    /** Through Africa's Talking. */
    public const LINE = 'line';

    /** operator_phone for a call made or taken on a command centre computer (browser softphone). */
    public const BROWSER = 'browser';

    /** From an operator's own phone, or typed in by whoever answered the hotline. */
    public const PHONE = 'phone';

    /** How an own-phone call went, as the operator reports it, and the status it leaves. */
    public const OUTCOMES = ['reached' => 'completed', 'no_answer' => 'no_answer', 'wrong_number' => 'wrong_number', 'call_later' => 'call_later'];

    /** Statuses that mean someone still has to be called (back). */
    public const FOLLOW_UP = ['missed', 'voicemail', 'no_answer', 'call_later'];

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

    /** Nobody has spoken to this person yet (a missed call, a voice message, no answer, "call later"), and nobody has dealt with it. */
    public function needsCallBack(): bool
    {
        return in_array($this->status, self::FOLLOW_UP, true) && $this->handled_at === null;
    }
}
