<?php

namespace App\Console\Commands;

use App\Enums\AreaLevel;
use App\Enums\Role;
use App\Models\Lga;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use App\Support\NigerianPhone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('members:import
    {csv : A group\'s member list: "Name of Group", "State" and "LGA" rows on top, then "S/N, Phone, Member Name, ..." rows}
    {--rejects= : Where to write the rows that could not be imported (default: next to the CSV)}
    {--dry-run : Report what would happen without saving anything}')]
#[Description('Create member accounts (phone sign-in, no password yet) from a group\'s member list (safe to re-run)')]
class ImportGroupMembers extends Command
{
    public function handle(): int
    {
        $csv = (string) $this->argument('csv');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_readable($csv) || ($handle = fopen($csv, 'r')) === false) {
            $this->components->error("Cannot read {$csv}");

            return self::FAILURE;
        }

        $meta = [];
        $columns = null;
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $values = array_map(fn ($v) => trim((string) $v, "\u{FEFF} \t"), $values);
            if ($columns === null) {
                if (Str::lower($values[0] ?? '') === 's/n') {
                    $columns = array_map(fn ($h) => Str::lower($h), $values);
                } elseif (($values[0] ?? '') !== '') {
                    // "Name of Group,,Team Kaka": the value is the first non-empty cell after the label.
                    $meta[Str::lower($values[0])] = collect(array_slice($values, 1))->first(fn ($v) => $v !== '') ?? '';
                }

                continue;
            }
            $rows[] = $values;
        }
        fclose($handle);

        $group = $meta['name of group'] ?? '';
        $phoneCol = $columns === null ? false : array_search('phone', $columns, true);
        $nameCol = $columns === null ? false : array_search('member name', $columns, true);
        if ($group === '' || $phoneCol === false || $nameCol === false) {
            $this->components->error('Expected a "Name of Group" row and a "S/N, Phone, Member Name" header row.');

            return self::FAILURE;
        }

        $stateName = trim(preg_replace('/\s+state$/i', '', $meta['state'] ?? ''));
        $stateName = in_array(Str::lower($stateName), ['fct', 'abuja'], true) ? 'Federal Capital Territory' : $stateName;
        $state = State::whereRaw('lower(name) = ?', [Str::lower($stateName)])->first();
        $lga = $state ? Lga::where('state_id', $state->id)->whereRaw('lower(name) = ?', [Str::lower($meta['lga'] ?? '')])->first() : null;
        if (! $state || ! $lga) {
            $this->components->error("Unknown state or LGA: \"{$meta['state']}\" / \"{$meta['lga']}\". Import geography first (geo:import).");

            return self::FAILURE;
        }

        $existing = User::whereNotNull('phone')->pluck('phone')->flip();
        $seen = [];
        $toCreate = [];
        $rejects = [];
        $alreadyRegistered = 0;

        foreach ($rows as $row) {
            $rawPhone = $row[$phoneCol] ?? '';
            $name = $this->tidyName($row[$nameCol] ?? '');
            if ($rawPhone === '' && $name === '') {
                continue;
            }

            $phone = NigerianPhone::normalise($rawPhone);
            $reason = match (true) {
                $phone === null => 'not a valid Nigerian mobile number',
                mb_strlen($name) < 3 => 'name missing',
                isset($seen[$phone]) => 'same phone as row '.$seen[$phone],
                default => null,
            };
            if ($reason !== null) {
                $rejects[] = [$row[0] ?? '', $rawPhone, $row[$nameCol] ?? '', $reason];

                continue;
            }
            $seen[$phone] = $row[0] ?? '?';

            if ($existing->has($phone)) {
                $alreadyRegistered++;

                continue;
            }
            $toCreate[] = ['phone' => $phone, 'name' => $name];
        }

        $this->components->twoColumnDetail('Group', "{$group} ({$lga->name}, {$state->name})");
        $this->components->twoColumnDetail('Rows read', (string) count($rows));
        $this->components->twoColumnDetail('New members', (string) count($toCreate));
        $this->components->twoColumnDetail('Already registered (skipped)', (string) $alreadyRegistered);
        $this->components->twoColumnDetail('Rejected (duplicate or bad phone, no name)', (string) count($rejects));

        if ($rejects !== []) {
            $path = (string) ($this->option('rejects') ?: preg_replace('/\.csv$/i', '', $csv).' - rejects.csv');
            if (! $dryRun) {
                $out = fopen($path, 'w');
                fputcsv($out, ['S/N', 'Phone', 'Member Name', 'Reason'], escape: '');
                foreach ($rejects as $reject) {
                    fputcsv($out, $reject, escape: '');
                }
                fclose($out);
            }
            $this->components->info(($dryRun ? 'Would write' : 'Wrote').' the rejected rows to '.$path);
        }

        if ($dryRun || $toCreate === []) {
            $dryRun && $this->components->warn('Dry run: nothing saved.');

            return self::SUCCESS;
        }

        // Nobody knows this password: members get their own by SMS (members:send-credentials).
        $lockedPassword = Hash::make(Str::random(64));
        $consentlessNow = now();
        $bar = $this->output->createProgressBar(count($toCreate));

        foreach (array_chunk($toCreate, 250) as $chunk) {
            DB::transaction(function () use ($chunk, $group, $state, $lga, $lockedPassword, $consentlessNow, $bar) {
                foreach ($chunk as $person) {
                    $user = new User(['name' => $person['name'], 'phone' => $person['phone']]);
                    $user->password = $lockedPassword;
                    $user->role = Role::Member;
                    $user->is_active = true;
                    $user->must_change_password = true;
                    $user->assignArea(AreaLevel::Lga, $lga->id);
                    $user->save();

                    $number = MemberProfile::newMemberNumber($state);
                    $profile = new MemberProfile;
                    $profile->user_id = $user->id;
                    $profile->member_number = $number;
                    $profile->referral_code = $number;
                    $profile->verify_token = MemberProfile::newVerifyToken();
                    $profile->has_pvc = false;
                    $profile->import_source = Str::limit($group, 64, '');
                    $profile->imported_at = $consentlessNow;
                    $profile->save();

                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info(count($toCreate)." members imported from {$group}. Send their sign-in details with: php artisan members:send-credentials --source=\"{$group}\"");

        return self::SUCCESS;
    }

    /** "AMINU SAIDU  HAFSAT " → "Aminu Saidu Hafsat"; a backtick used as an apostrophe becomes one. */
    private function tidyName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', str_replace('`', "'", $name)));

        return Str::limit(Str::title(Str::lower($name)), 120, '');
    }
}
