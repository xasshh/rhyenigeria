<?php

namespace App\Services\Voice;

use App\Models\User;
use App\Support\NigerianPhone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Places calls through Africa's Talking Voice (https://developers.africastalking.com/docs/voice).
 * A call rings one phone; when it is answered AT asks our webhook what to do, and we connect it.
 */
class AfricasTalkingVoice
{
    public function isConfigured(): bool
    {
        return filled(config('services.africastalking.username'))
            && filled(config('services.africastalking.api_key'))
            && filled(config('services.africastalking.voice_number'))
            && filled(config('services.africastalking.webhook_token'));
    }

    public function number(): ?string
    {
        return config('services.africastalking.voice_number');
    }

    /** The command centre's own phones (AT_VOICE_FORWARD_TO): they ring for incoming calls and for click-to-call. */
    public function commandCentrePhones(): array
    {
        return collect(explode(',', (string) config('services.africastalking.forward_to')))
            ->map(fn ($n) => NigerianPhone::normalise(trim($n)) ?? (str_starts_with(trim($n), '+') ? trim($n) : null))
            ->filter()->values()->all();
    }

    /** The phone that rings when an operator clicks Call (the primary desk phone). */
    public function deskPhone(): ?string
    {
        return $this->commandCentrePhones()[0] ?? null;
    }

    /** How long a browser softphone counts as online after its last check-in. */
    private const ONLINE_SECONDS = 75;

    private const ONLINE_KEY = 'voice:softphones';

    /** The name a staff member's browser softphone is known by at Africa's Talking (letters and digits only). */
    public static function clientName(User $user): string
    {
        return 'staff'.$user->id;
    }

    /** The address that rings a staff member's browser softphone in a Dial: "<AT username>.<client name>". */
    public function clientAddress(int $userId): string
    {
        return config('services.africastalking.username').'.staff'.$userId;
    }

    /** The staff member behind a browser softphone address AT reports (e.g. "UbasaniCommandCentre.staff12"), if it is one. */
    public static function staffIdFromClient(?string $address): ?int
    {
        return preg_match('/(?:^|[.:])staff(\d+)(?:@|$)/', (string) $address, $m) ? (int) $m[1] : null;
    }

    /**
     * A capability token that lets this staff member's browser make and take calls on the campaign line.
     *
     * @throws RuntimeException when AT does not issue one
     */
    public function capabilityToken(User $user, int $seconds = 14400): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The phone line is not set up yet.');
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(15)
                ->withHeaders(['apiKey' => (string) config('services.africastalking.api_key')])
                ->post('https://webrtc.africastalking.com/capability-token/request', [
                    'username' => (string) config('services.africastalking.username'),
                    'clientName' => self::clientName($user),
                    'phoneNumber' => $this->number(),
                    'incoming' => true,
                    'outgoing' => true,
                    'expire' => $seconds.'s',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Africa\'s Talking. Try again.');
        }

        $token = $response->json('token');
        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Africa\'s Talking would not start computer calling: '.($response->json('errorMessage') ?? $response->json('message') ?? 'HTTP '.$response->status()));
        }

        return $token;
    }

    /** A browser softphone checked in (or signed off): only online softphones ring for calls into the line. */
    public function markOnline(int $userId, bool $online = true): void
    {
        Cache::lock(self::ONLINE_KEY.':lock', 5)->block(3, function () use ($userId, $online) {
            $seen = array_filter((array) Cache::get(self::ONLINE_KEY, []), fn ($at) => $at > now()->timestamp - self::ONLINE_SECONDS);
            if ($online) {
                $seen[$userId] = now()->timestamp;
            } else {
                unset($seen[$userId]);
            }
            Cache::put(self::ONLINE_KEY, $seen, now()->addHour());
        });
    }

    /** @return list<int> Staff whose browser softphone is open and ready right now. */
    public function onlineStaffIds(): array
    {
        $seen = (array) Cache::get(self::ONLINE_KEY, []);

        return array_values(array_map('intval', array_keys(array_filter($seen, fn ($at) => $at > now()->timestamp - self::ONLINE_SECONDS))));
    }

    /**
     * Ring a phone from the line. Returns AT's session id, which its callbacks carry.
     *
     * @throws RuntimeException when AT does not accept the call
     */
    public function call(string $to): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The phone line is not set up yet.');
        }

        $username = (string) config('services.africastalking.username');
        $host = $username === 'sandbox' ? 'https://voice.sandbox.africastalking.com' : 'https://voice.africastalking.com';

        try {
            $response = Http::asForm()->acceptJson()->timeout(15)
                ->withHeaders(['apiKey' => (string) config('services.africastalking.api_key')])
                ->post($host.'/call', ['username' => $username, 'from' => $this->number(), 'to' => $to]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Africa\'s Talking. Try again.');
        }

        $entry = $response->json('entries.0');
        if ($response->failed() || ! is_array($entry) || blank($entry['sessionId'] ?? null) || ($entry['sessionId'] ?? '') === 'None') {
            $why = $entry['status'] ?? $response->json('errorMessage') ?? mb_strimwidth($response->body(), 0, 160, '…');

            throw new RuntimeException('Africa\'s Talking did not place the call: '.$why);
        }

        return (string) $entry['sessionId'];
    }

    /**
     * The XML that tells AT to connect the current call to these numbers. With $fallback, a voice-message
     * prompt follows the Dial, for when nobody picks up and AT carries on to the next action.
     */
    public static function dial(array $numbers, ?string $say = null, ?string $fallback = null, bool $sequential = true): string
    {
        // sequential="false" rings every number at once (browsers and the desk phone); the first to answer gets the call.
        $attrs = 'phoneNumbers="'.e(implode(',', $numbers)).'" record="true" sequential="'.($sequential ? 'true' : 'false').'"';
        if ($callerId = config('services.africastalking.voice_number')) {
            $attrs .= ' callerId="'.e($callerId).'"';
        }

        return self::response(($say ? '<Say>'.e($say).'</Say>' : '').'<Dial '.$attrs.'/>'.($fallback ? self::record($fallback) : ''));
    }

    /** The XML that takes a voice message. */
    public static function voicemail(string $say): string
    {
        return self::response(self::record($say));
    }

    private static function record(string $say): string
    {
        return '<Record finishOnKey="#" maxLength="90" trimSilence="true" playBeep="true"><Say>'.e($say).'</Say></Record>';
    }

    /** Turn the call away without answering it. */
    public static function reject(): string
    {
        return self::response('<Reject/>');
    }

    public static function say(string $say): string
    {
        return self::response('<Say>'.e($say).'</Say>');
    }

    private static function response(string $actions): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$actions.'</Response>';
    }
}
