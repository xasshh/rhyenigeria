<?php

namespace App\Console\Commands;

use App\Models\VoiceCall;
use App\Services\Voice\AfricasTalkingVoice;
use App\Support\NigerianPhone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/** Ring a phone from the campaign line; when answered it plays a short "the line works" message. */
#[Signature('voice:test {phone : Nigerian mobile number to ring}')]
#[Description('Place a test call from the campaign phone line (Africa\'s Talking)')]
class VoiceTestCall extends Command
{
    public const NOTE = 'Line test';

    public function handle(AfricasTalkingVoice $voice): int
    {
        $phone = NigerianPhone::normalise((string) $this->argument('phone'));
        if ($phone === null) {
            $this->error('That is not a Nigerian mobile number.');

            return self::FAILURE;
        }

        try {
            $sessionId = $voice->call($phone);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        VoiceCall::create([
            'session_id' => $sessionId, 'direction' => VoiceCall::OUTBOUND, 'channel' => VoiceCall::LINE,
            'status' => 'ringing', 'contact_phone' => $phone, 'note' => self::NOTE,
        ]);
        $this->info("Ringing {$phone} from {$voice->number()} (session {$sessionId}).");

        return self::SUCCESS;
    }
}
