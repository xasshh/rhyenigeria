<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Sms\BulkSmsNigeria;
use App\Support\NigerianPhone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Throwable;

#[Signature('members:send-credentials
    {--source= : Only members imported from this group, e.g. --source="Team Kaka"}
    {--phone=* : Only these phone numbers (use this to resend to someone who lost their SMS)}
    {--limit= : Send at most this many (try a handful first)}
    {--resend : Include members who were already sent their details}
    {--pause=700 : Milliseconds between messages (the API allows about 100 requests a minute)}
    {--dry-run : Show who would get an SMS and one sample message, without sending or changing passwords}')]
#[Description('Give imported members a temporary password and SMS it to them with their sign-in details')]
class SendMemberCredentials extends Command
{
    /** No 0/o, 1/l/i: easy to read off an SMS and type on a phone keypad. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** One SMS page: GSM-7, 160 characters. Longer messages cost two units each. */
    private const MAX_LENGTH = 160;

    public function handle(BulkSmsNigeria $sms): int
    {
        $phones = collect((array) $this->option('phone'))->map(fn ($p) => NigerianPhone::normalise((string) $p));
        if ($phones->contains(null)) {
            $this->components->error('Every --phone must be a Nigerian mobile number.');

            return self::FAILURE;
        }
        if ($this->option('source') === null && $phones->isEmpty()) {
            $this->components->error('Pass --source="<group>" or --phone=<number>.');

            return self::FAILURE;
        }

        $query = User::query()
            ->where('role', Role::Member)
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->whereHas('memberProfile', function (Builder $q) {
                $q->whereNotNull('import_source');
                $this->option('source') !== null && $q->where('import_source', (string) $this->option('source'));
                $this->option('resend') || $this->option('phone') || $q->whereNull('credentials_sent_at');
            })
            ->when($phones->isNotEmpty(), fn ($q) => $q->whereIn('phone', $phones->all()))
            ->with('memberProfile')
            ->orderBy('id');

        $total = (clone $query)->count();
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $count = $limit !== null ? min($limit, $total) : $total;

        $this->components->twoColumnDetail('Members to message', (string) $count);
        if ($count === 0) {
            return self::SUCCESS;
        }

        $sample = $query->first();
        $this->components->twoColumnDetail('Sample message', $this->message($sample, 'abcd2345'));
        $this->components->twoColumnDetail('Estimated cost', '₦'.number_format($count * 6.49 * 1.075, 2).' (₦6.49 + 7.5% VAT each)');

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run: nothing sent, no passwords changed.');

            return self::SUCCESS;
        }
        if (! $sms->isConfigured()) {
            $this->components->error('Set BULKSMSNIGERIA_API_TOKEN (and an approved BULKSMSNIGERIA_SENDER_ID) in .env first.');

            return self::FAILURE;
        }

        $balance = $sms->balance();
        if ($balance !== null) {
            $this->components->twoColumnDetail('BulkSMS Nigeria balance', '₦'.number_format($balance, 2));
        }

        $pause = max(0, (int) $this->option('pause')) * 1000;
        $sent = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($count);

        foreach ($query->limit($count)->get() as $user) {
            $password = $this->temporaryPassword();

            try {
                // Send first: the account only gets the new password once the SMS is accepted.
                $sms->send($user->phone, $this->message($user, $password));
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->components->error(NigerianPhone::mask($user->phone).': '.$e->getMessage());

                // A wallet or sender ID problem will fail every message: stop instead of burning through the list.
                if (str_contains($e->getMessage(), 'BSNG-3000') || str_contains($e->getMessage(), 'BSNG-2011') || str_contains($e->getMessage(), 'BSNG-1000')) {
                    break;
                }

                continue;
            }

            $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
            $user->tokens()->delete();
            $user->memberProfile->forceFill(['credentials_sent_at' => now()])->save();
            $sent++;
            $bar->advance();
            $pause && usleep($pause);
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Sent {$sent}, failed {$failed}. Re-run the same command to retry the ones that failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function temporaryPassword(): string
    {
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $password;
    }

    /** Fits one SMS page; drops the greeting name if a long name would push it to two. */
    public function message(User $user, string $password): string
    {
        $site = preg_replace('#^https?://(www\.)?#', '', rtrim((string) config('app.url'), '/'));
        $phone = '0'.substr((string) $user->phone, 4);
        $body = "your RHYE account is ready. Sign in at {$site}/login Phone: {$phone} Password: {$password} Then choose your own password.";

        $first = $this->gsmSafe(strtok((string) $user->name, ' ') ?: '');
        $message = "Hi {$first}, {$body}";

        return mb_strlen($message) <= self::MAX_LENGTH && $first !== '' ? $message : 'Hi, '.$body;
    }

    private function gsmSafe(string $text): string
    {
        return (string) preg_replace('/[^A-Za-z\'\-]/', '', $text);
    }
}
