<?php

namespace App\Console\Commands;

use App\Services\Geo\GeoImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('geo:import
    {level : What the file contains: state, lga, ward or polling-unit}
    {file : Path to a .csv or .geojson file}
    {--dry-run : Report what would change without saving anything}')]
#[Description('Import states, LGAs, wards or polling units from CSV or GeoJSON (safe to re-run)')]
class ImportGeography extends Command
{
    public function handle(GeoImporter $importer): int
    {
        $level = (string) $this->argument('level');
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importer->import($level, $file, $dryRun);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(($dryRun ? 'Dry run - nothing was saved. ' : '')."Imported {$level} rows from {$file}");

        $this->table(['Created', 'Updated', 'Skipped', 'of which unchanged', 'of which invalid', 'Rows read'], [[
            $result->created,
            $result->updated,
            $result->skipped(),
            $result->unchanged,
            $result->invalid,
            $result->total(),
        ]]);

        if ($result->problems !== []) {
            $this->components->warn('Rows that were skipped as invalid'.($result->invalid > count($result->problems) ? ' (first '.count($result->problems).')' : '').':');
            foreach ($result->problems as $problem) {
                $this->line('  '.$problem);
            }
        }

        return self::SUCCESS;
    }
}
