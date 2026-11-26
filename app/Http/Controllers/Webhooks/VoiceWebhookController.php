<?php

namespace App\Http\Controllers\Webhooks;

use App\Console\Commands\VoiceTestCall;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Voice\AfricasTalkingVoice;
use App\Support\NigerianPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Africa's Talking calls this whenever something happens on the phone line: a call is answered
 * (isActive=1, we reply with what to do) and when it ends (isActive=0, with duration, cost and the
 * recording). AT does not sign its requests, so the URL carries a secret token.
 */
class VoiceWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $expected = (string) config('services.africastalking.webhook_token');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);

        $sessionId = (string) $request->input('sessionId');
        abort_if($sessionId === '', 422);

        $call = VoiceCall::where('session_id', $sessionId)->first();

        if ($request->input('isActive') === '0' || $request->input('isActive') === 0) {
            $call ??= $this->inbound($request, $sessionId);
            $this->finish($call, $request);

            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response/>');
        }

        // A test call (php artisan voice:test): say the line works.
        if ($call?->direction === VoiceCall::OUTBOUND && $call->operator_id === null && $call->note === VoiceTestCall::NOTE) {
            $call->forceFill(['status' => 'connected', 'answered_at' => now()])->save();

            return $this->xml(AfricasTalkingVoice::say('Hello. This is a test call from the '.config('site.name').' command centre. The campaign phone line is working. Thank you, goodbye.'));
        }

        // The operator picked up: put them through to the person they asked to call.
        if ($call?->direction === VoiceCall::OUTBOUND) {
            if ($call->status === 'ringing') {
                $call->forceFill(['status' => 'connected', 'answered_at' => now()])->save();
            }

            return $this->xml(AfricasTalkingVoice::dial([$call->contact_phone], 'Connecting your call.'));
        }

        if ($call === null && strtolower((string) $request->input('direction')) === 'outbound') {
            return $this->xml(AfricasTalkingVoice::say('This call has ended.'));
        }

        // Someone rang the line.
        $call ??= $this->inbound($request, $sessionId);
        $greeting = config('services.africastalking.greeting') ?: 'Thank you for calling the '.config('site.name').' command centre.';
        $forward = collect(explode(',', (string) config('services.africastalking.forward_to')))
            ->map(fn ($n) => NigerianPhone::normalise(trim($n)) ?? (str_starts_with(trim($n), '+') ? trim($n) : null))
            ->filter()->values()->all();

        return $this->xml($forward
            ? AfricasTalkingVoice::dial($forward, $greeting.' Please hold while we connect you.')
            : AfricasTalkingVoice::voicemail($greeting.' Please leave your name, your ward and your message after the beep, then press the hash key. We will call you back.'));
    }

    private function inbound(Request $request, string $sessionId): VoiceCall
    {
        $raw = (string) $request->input('callerNumber');
        $phone = NigerianPhone::normalise($raw) ?? mb_substr($raw, 0, 20);

        return VoiceCall::create([
            'session_id' => $sessionId,
            'direction' => VoiceCall::INBOUND,
            'status' => 'ringing',
            'contact_phone' => $phone ?: 'Unknown',
            'contact_user_id' => $phone ? User::where('phone', $phone)->value('id') : null,
        ]);
    }

    /** How much a final status says happened; used so a repeated report cannot downgrade a call. */
    private const RANK = ['failed' => 1, 'missed' => 1, 'no_answer' => 1, 'voicemail' => 2, 'completed' => 3];

    private function finish(VoiceCall $call, Request $request): void
    {
        $dialled = $request->filled('dialDurationInSeconds') ? (int) $request->input('dialDurationInSeconds') : null;
        $recording = $request->input('recordingUrl') ?: $call->recording_url;

        $status = match (true) {
            $call->note === VoiceTestCall::NOTE => in_array($call->status, ['connected', 'completed'], true) ? 'completed' : 'no_answer',
            $call->direction === VoiceCall::OUTBOUND && $call->status === 'ringing' => 'failed', // the operator never picked up
            $call->direction === VoiceCall::OUTBOUND => ($dialled ?? 0) > 0 ? 'completed' : 'no_answer',
            ($dialled ?? 0) > 0 => 'completed',
            $recording !== null => 'voicemail',
            default => 'missed',
        };

        // AT can send the end-of-call report more than once, sometimes with less in it: a repeat never downgrades the call.
        if ($call->ended_at !== null && (self::RANK[$status] ?? 0) < (self::RANK[$call->status] ?? 0)) {
            $status = $call->status;
        }

        $call->forceFill([
            'status' => $status,
            'duration_seconds' => $dialled ?? ($request->filled('durationInSeconds') ? (int) $request->input('durationInSeconds') : $call->duration_seconds),
            'recording_url' => $recording ? mb_substr((string) $recording, 0, 500) : null,
            'cost' => is_numeric($request->input('amount')) ? $request->input('amount') : $call->cost,
            'currency' => $request->input('currencyCode') ? mb_substr((string) $request->input('currencyCode'), 0, 3) : $call->currency,
            'hangup_cause' => $request->input('hangupCause') ? mb_substr((string) $request->input('hangupCause'), 0, 100) : $call->hangup_cause,
            'answered_at' => $call->answered_at ?? ($status === 'completed' ? now()->subSeconds((int) $dialled) : null),
            'ended_at' => now(),
        ])->save();
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml']);
    }
}
