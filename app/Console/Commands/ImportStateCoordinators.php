<?php

namespace App\Console\Commands;

use App\Models\State;
use App\Support\WebImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('coordinators:import
    {csv : The "RHYE State Coordinators\' Information Form" CSV export from Google Forms}
    {--photos= : Folder holding the form\'s "Passport photograph (File responses)" downloads}
    {--photo=* : Pair a photo with a state when its file name does not end in the coordinator\'s name, e.g. --photo="Ebonyi=PASSPORT - Jedi Food Company.jpg"}
    {--dry-run : Report what would change without saving anything}')]
#[Description('Set each state\'s coordinator name, photo and WhatsApp link from the coordinators\' form (safe to re-run)')]
class ImportStateCoordinators extends Command
{
    private const WHATSAPP = '/^https:\/\/(chat\.whatsapp\.com|wa\.me|whatsapp\.com\/channel)\//';

    public function handle(): int
    {
        $csv = (string) $this->argument('csv');
        $photoDir = $this->option('photos') ? rtrim((string) $this->option('photos'), '/') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (! is_readable($csv) || ($handle = fopen($csv, 'r')) === false) {
            $this->components->error("Cannot read {$csv}");

            return self::FAILURE;
        }
        if ($photoDir !== null && ! is_dir($photoDir)) {
            $this->components->error("No such folder: {$photoDir}");

            return self::FAILURE;
        }

        $overrides = [];
        foreach ((array) $this->option('photo') as $pair) {
            [$stateName, $file] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $overrides[Str::lower(trim($stateName))] = trim($file);
        }

        $header = array_map(fn ($h) => trim((string) $h, "\u{FEFF} \""), fgetcsv($handle, escape: '') ?: []);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) === count($header)) {
                $rows[] = array_combine($header, array_map(fn ($v) => trim((string) $v), $values));
            }
        }
        fclose($handle);

        $states = State::all()->keyBy(fn (State $s) => Str::lower($s->name));
        $report = [];
        $warnings = [];

        foreach ($rows as $row) {
            $stateKey = Str::lower($row['State'] ?? '');
            $stateKey = $stateKey === 'fct' || $stateKey === 'abuja' ? 'federal capital territory' : $stateKey;
            $state = $states->get($stateKey);
            $name = $this->tidyName($row['Full name'] ?? '');

            if (! $state || $name === '') {
                $warnings[] = "Skipped \"{$row['Full name']}\": unknown state \"{$row['State']}\".";

                continue;
            }

            $link = $row['WhatsApp group link for your State Volunteer Group'] ?? '';
            $link = preg_match(self::WHATSAPP, $link) ? $link : null;
            if ($link === null) {
                $warnings[] = "{$state->name}: \"".($row['WhatsApp group link for your State Volunteer Group'] ?? '')."\" is not a WhatsApp invite link; the state's existing link is kept.";
            }

            $photo = $photoDir !== null ? $this->findPhoto($photoDir, $name, $overrides[Str::lower($state->name)] ?? null) : null;
            if ($photoDir !== null && $photo === null) {
                $warnings[] = "{$state->name}: no photo found for {$name}; pass --photo=\"{$state->name}=<file name>\".";
            }

            $report[] = [$state->name, $name, $link ? 'yes' : 'kept', $photo ? basename($photo) : '-'];

            if ($dryRun) {
                continue;
            }

            $state->coordinator_name = $name;
            if ($link !== null) {
                $state->whatsapp_group_url = $link;
            }
            if ($photo !== null) {
                Storage::disk('public')->delete(array_filter([$state->coordinator_photo_path]));
                // Same treatment as an upload in Settings: keep only the 600 px WebP copy.
                [$original, $state->coordinator_photo_path] = WebImage::storeWithWebp(new UploadedFile($photo, basename($photo), test: true), 'coordinators', 600);
                Storage::disk('public')->delete($original);
            }
            $state->save();
        }

        $this->components->info(($dryRun ? 'Dry run - nothing was saved. ' : '').count($report).' state coordinator(s) from '.basename($csv));
        $this->table(['State', 'Coordinator', 'WhatsApp link', 'Photo'], $report);
        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }

    /** "MICHAEL NWITE " becomes "Michael Nwite"; names already in mixed case are left alone. */
    private function tidyName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        return $name === Str::upper($name) ? Str::title(Str::lower($name)) : $name;
    }

    /** Google Forms saves uploads as "<original name> - <respondent's name>.<ext>". */
    private function findPhoto(string $dir, string $name, ?string $override): ?string
    {
        if ($override !== null) {
            return is_file($dir.'/'.$override) ? $dir.'/'.$override : null;
        }

        foreach (glob($dir.'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [] as $file) {
            $suffix = Str::afterLast(pathinfo($file, PATHINFO_FILENAME), ' - ');
            if (Str::lower(trim($suffix)) === Str::lower($name)) {
                return $file;
            }
        }

        return null;
    }
}
