<?php

namespace App\Services\Voice;

use Illuminate\Http\Client\ConnectionException;
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

    /** The XML that tells AT to connect the current call to these numbers. */
    public static function dial(array $numbers, ?string $say = null): string
    {
        $attrs = 'phoneNumbers="'.e(implode(',', $numbers)).'" record="true" sequential="true"';
        if ($callerId = config('services.africastalking.voice_number')) {
            $attrs .= ' callerId="'.e($callerId).'"';
        }

        return self::response(($say ? '<Say>'.e($say).'</Say>' : '').'<Dial '.$attrs.'/>');
    }

    /** The XML that takes a voice message. */
    public static function voicemail(string $say): string
    {
        return self::response('<Record finishOnKey="#" maxLength="90" trimSilence="true" playBeep="true"><Say>'.e($say).'</Say></Record>');
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
