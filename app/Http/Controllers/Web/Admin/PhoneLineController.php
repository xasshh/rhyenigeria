<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Audit\AuditLogger;
use App\Services\Voice\AfricasTalkingVoice;
use App\Support\NigerianPhone;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The command centre's phone line. Placing a call rings the operator's phone first and,
 * when they answer, connects the contact (so every call goes out from the campaign number,
 * is recorded and is logged). Calls into the line show up here as they ring.
 */
class PhoneLineController extends Controller
{
    public function index(): JsonResponse
    {
        $tz = config('membership.timezone');
        $calls = VoiceCall::with('contact:id,name,role,lga_id,ward_id', 'contact.lga:id,name', 'contact.ward:id,name', 'operator:id,name')
            ->latest('id')->limit(25)->get();

        return response()->json([
            'calls' => $calls->map(fn (VoiceCall $c) => [
                'id' => $c->id,
                'direction' => $c->direction,
                'status' => $c->status,
                'live' => $c->isLive(),
                'needs_call_back' => $c->needsCallBack(),
                'name' => $c->contact?->name,
                'area' => $c->contact ? collect([$c->contact->ward?->name, $c->contact->lga?->name])->filter()->implode(', ') : null,
                'phone' => $c->contact_phone,
                'phone_display' => NigerianPhone::format($c->contact_phone),
                'operator' => $c->operator?->name,
                'duration' => $c->duration_seconds,
                'recording' => $c->recording_url,
                'handled_by' => $c->handled_at ? 'Handled' : null,
                'time' => CarbonImmutable::parse($c->created_at)->setTimezone($tz)->format('H:i'),
                'day' => CarbonImmutable::parse($c->created_at)->setTimezone($tz)->isToday() ? null : CarbonImmutable::parse($c->created_at)->setTimezone($tz)->format('j M'),
            ])->values(),
            'waiting' => VoiceCall::where('direction', VoiceCall::INBOUND)->whereIn('status', ['missed', 'voicemail'])->whereNull('handled_at')->count(),
        ])->setPrivate();
    }

    public function store(Request $request, AfricasTalkingVoice $voice, AuditLogger $audit): JsonResponse
    {
        if (! $voice->isConfigured()) {
            return response()->json(['message' => 'The phone line is not set up yet. Use your own phone for now.'], 503);
        }

        $request->merge(['ring_me' => NigerianPhone::normalise($request->input('ring_me')) ?? $request->input('ring_me')]);
        $data = $request->validate([
            'user_id' => ['required_without:call_id', 'nullable', 'integer'],
            'call_id' => ['required_without:user_id', 'nullable', 'integer'],
            'ring_me' => ['required', 'regex:/^\+234[789][01]\d{8}$/'],
        ], ['ring_me.required' => 'Enter the phone number we should ring you on.', 'ring_me.regex' => 'Enter a Nigerian mobile number to ring you on.']);

        [$phone, $contactId] = isset($data['user_id']) ? $this->contact((int) $data['user_id']) : $this->caller((int) $data['call_id']);

        try {
            $sessionId = $voice->call($data['ring_me']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $call = VoiceCall::create([
            'session_id' => $sessionId,
            'direction' => VoiceCall::OUTBOUND,
            'status' => 'ringing',
            'contact_phone' => $phone,
            'contact_user_id' => $contactId,
            'operator_id' => $request->user()->id,
            'operator_phone' => $data['ring_me'],
        ]);

        // Calling someone back deals with their missed call.
        if (isset($data['call_id'])) {
            VoiceCall::whereKey($data['call_id'])->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);
        }

        $audit->record($request, AuditLogger::COMMAND_CALL_PLACED, $call, ['to' => NigerianPhone::mask($phone)]);

        return response()->json(['message' => 'Your phone ('.NigerianPhone::format($data['ring_me']).') is ringing. Answer it and we will connect the call.', 'id' => $call->id]);
    }

    public function handled(Request $request, VoiceCall $call): JsonResponse
    {
        abort_unless($call->direction === VoiceCall::INBOUND, 404);
        $call->forceFill(['handled_at' => now(), 'handled_by' => $request->user()->id])->save();

        return response()->json(['ok' => true]);
    }

    /** @return array{0: string, 1: int} Someone the call desk lists (same scope as the contacts search). */
    private function contact(int $id): array
    {
        $user = User::query()->whereKey($id)->where('is_active', true)->whereNotNull('phone')
            ->whereIn('role', [Role::Coordinator->value, Role::Member->value])
            ->when(site_state_code(), fn ($q) => $q->whereHas('state', fn ($s) => $s->where('code', site_state_code())))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['user_id' => 'That person cannot be called from here.']);
        }

        return [$user->phone, $user->id];
    }

    /** @return array{0: string, 1: ?int} Someone who rang the line. */
    private function caller(int $id): array
    {
        $call = VoiceCall::whereKey($id)->where('direction', VoiceCall::INBOUND)->first();
        if (! $call || ! str_starts_with($call->contact_phone, '+')) {
            throw ValidationException::withMessages(['call_id' => 'That caller cannot be called back.']);
        }

        return [$call->contact_phone, $call->contact_user_id];
    }
}
