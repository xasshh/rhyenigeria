<?php

namespace App\Services\Membership;

use App\Models\User;
use App\Services\Sms\BulkSmsNigeria;
use App\Support\NigerianPhone;
use Illuminate\Support\Facades\Hash;
use Throwable;

/** Temporary passwords for accounts a coordinator creates; the person picks their own on first sign-in. */
class TemporaryCredentials
{
    /** No 0/o, 1/l/i: easy to read off a message and type on a phone keypad. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(private BulkSmsNigeria $sms) {}

    /** Give the user a new temporary password and sign them out everywhere. Returns the password. */
    public function issue(User $user): string
    {
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
        $user->tokens()->delete();

        return $password;
    }

    public function message(User $user, string $password, string $roleLabel): string
    {
        $site = preg_replace('#^https?://(www\.)?#', '', rtrim((string) config('app.url'), '/'));

        return "Hello {$user->firstName()}, you are now {$roleLabel} on RHYE. Sign in at {$site}/login with your phone number "
            .NigerianPhone::format($user->phone)." and this temporary password: {$password}. You will choose your own password after signing in.";
    }

    /** A wa.me link that opens WhatsApp with the message ready to send to the person. */
    public function whatsappUrl(User $user, string $message): string
    {
        return 'https://wa.me/'.ltrim((string) $user->phone, '+').'?text='.rawurlencode($message);
    }

    public function canSms(): bool
    {
        return $this->sms->isConfigured();
    }

    /** Short enough for one or two SMS pages. Returns an error message, or null when it was sent. */
    public function sendSms(User $user, string $password): ?string
    {
        $site = preg_replace('#^https?://(www\.)?#', '', rtrim((string) config('app.url'), '/'));

        try {
            $this->sms->send($user->phone, "RHYE: sign in at {$site}/login with ".NigerianPhone::format($user->phone)." and password {$password}, then choose your own.");

            return null;
        } catch (Throwable $e) {
            report($e);

            return 'The SMS could not be sent. Share the details another way.';
        }
    }
}
