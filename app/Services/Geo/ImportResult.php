<?php

namespace App\Services\Geo;

class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    /** Rows already present with identical data. */
    public int $unchanged = 0;

    /** Rows that could not be imported. */
    public int $invalid = 0;

    /** @var list<string> Why rows were invalid (first 50). */
    public array $problems = [];

    public function skipped(): int
    {
        return $this->unchanged + $this->invalid;
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped();
    }

    public function problem(int $row, string $message): void
    {
        $this->invalid++;

        if (count($this->problems) < 50) {
            $this->problems[] = "Row {$row}: {$message}";
        }
    }
}
