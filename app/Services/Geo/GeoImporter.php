<?php

namespace App\Services\Geo;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Imports states, LGAs, wards and polling units from CSV or GeoJSON.
 *
 * Rows are matched on their official code within their parent, so running the
 * same file again changes nothing, and running a corrected file updates only
 * the rows that differ. A row with no geometry never erases a stored one.
 */
class GeoImporter
{
    public const LEVELS = ['state', 'lga', 'ward', 'polling-unit'];

    private const TABLES = [
        'state' => ['table' => 'states', 'parent' => null, 'parents' => []],
        'lga' => ['table' => 'lgas', 'parent' => 'state_id', 'parents' => ['state_code']],
        'ward' => ['table' => 'wards', 'parent' => 'lga_id', 'parents' => ['state_code', 'lga_code']],
        'polling-unit' => ['table' => 'polling_units', 'parent' => 'ward_id', 'parents' => ['state_code', 'lga_code', 'ward_code']],
    ];

    // Source boundary files often contain small defects (self-touching rings and
    // the like), so boundaries are repaired on the way in; anything that is not
    // a polygon after repair is rejected.
    private const BOUNDARY_SQL = 'ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_SetSRID(%s, 4326)), 3))';

    /** @var array<string, int|null> */
    private array $parentCache = [];

    public function import(string $level, string $path, bool $dryRun = false): ImportResult
    {
        if (! isset(self::TABLES[$level])) {
            throw new InvalidArgumentException('Level must be one of: '.implode(', ', self::LEVELS));
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("File not found or not readable: {$path}");
        }

        $rows = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv' => $this->readCsv($path),
            'geojson', 'json' => $this->readGeoJson($path),
            default => throw new InvalidArgumentException('Unsupported file type: use .csv, .geojson or .json'),
        };

        $this->parentCache = [];
        $result = new ImportResult;

        DB::beginTransaction();

        try {
            foreach ($rows as $number => $row) {
                $this->importRow($level, $number, $row, $result);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $result;
    }

    /** @param array<string, mixed> $row */
    private function importRow(string $level, int $number, array $row, ImportResult $result): void
    {
        $definition = self::TABLES[$level];
        $code = trim((string) ($row['code'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));

        if ($code === '' || $name === '') {
            $result->problem($number, 'missing code or name');

            return;
        }

        foreach ($definition['parents'] as $column) {
            if (trim((string) ($row[$column] ?? '')) === '') {
                $result->problem($number, "missing {$column}");

                return;
            }
        }

        $parentId = $this->resolveParent($level, $row);
        if ($definition['parent'] !== null && $parentId === null) {
            $result->problem($number, 'parent not found ('.implode('/', array_map(fn ($c) => (string) $row[$c], $definition['parents'])).') - import the level above first');

            return;
        }

        // Geometry to write: column => [sql, bindings].
        $geometry = [];

        if ($level === 'polling-unit') {
            $point = $this->pointFrom($row);
            if ($point === false) {
                $result->problem($number, 'invalid latitude/longitude');

                return;
            }
            if ($point !== null) {
                $geometry['location'] = ['ST_SetSRID(ST_MakePoint(?, ?), 4326)', [$point[1], $point[0]]];
            }
        }

        if (($row['_geometry'] ?? null) !== null) {
            $type = $row['_geometry']['type'] ?? null;
            if (in_array($type, ['Polygon', 'MultiPolygon'], true)) {
                $geometry['boundary'] = [sprintf(self::BOUNDARY_SQL, 'ST_GeomFromGeoJSON(?)'), [json_encode($row['_geometry'])]];
            } elseif (! ($level === 'polling-unit' && $type === 'Point')) {
                $result->problem($number, "unsupported geometry type {$type}");

                return;
            }
        } elseif (trim((string) ($row['boundary_wkt'] ?? '')) !== '') {
            $geometry['boundary'] = [sprintf(self::BOUNDARY_SQL, 'ST_GeomFromText(?)'), [trim($row['boundary_wkt'])]];
        }

        try {
            // A savepoint per row, so one bad geometry does not abort the whole import.
            $outcome = DB::transaction(function () use ($definition, $parentId, $code, $name, $geometry) {
                if (isset($geometry['boundary']) && DB::scalar("SELECT ST_IsEmpty({$geometry['boundary'][0]})", $geometry['boundary'][1])) {
                    throw new DomainException('the boundary is not a usable polygon');
                }

                return $this->upsert($definition, $parentId, $code, $name, $geometry);
            });
        } catch (DomainException $e) {
            $result->problem($number, 'invalid geometry: '.$e->getMessage());

            return;
        } catch (QueryException $e) {
            $result->problem($number, 'invalid geometry or data: '.$this->shortError($e));

            return;
        }

        $result->{$outcome}++;
    }

    /**
     * @param  array{table: string, parent: ?string, parents: list<string>}  $definition
     * @param  array<string, array{0: string, 1: list<mixed>}>  $geometry
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsert(array $definition, ?int $parentId, string $code, string $name, array $geometry): string
    {
        $table = $definition['table'];
        $now = now('UTC')->format('Y-m-d H:i:s');

        $where = 'code = ?';
        $whereBindings = [$code];
        if ($definition['parent'] !== null) {
            $where = "{$definition['parent']} = ? AND code = ?";
            $whereBindings = [$parentId, $code];
        }

        $comparisons = '';
        $comparisonBindings = [];
        foreach ($geometry as $column => [$sql, $bindings]) {
            $comparisons .= ", COALESCE(ST_OrderingEquals({$column}, {$sql}), false) AS same_{$column}";
            array_push($comparisonBindings, ...$bindings);
        }

        $existing = DB::selectOne(
            "SELECT id, name{$comparisons} FROM {$table} WHERE {$where} LIMIT 1",
            [...$comparisonBindings, ...$whereBindings],
        );

        if ($existing === null) {
            $columns = ['code', 'name', 'created_at', 'updated_at'];
            $values = ['?', '?', '?', '?'];
            $bindings = [$code, $name, $now, $now];

            if ($definition['parent'] !== null) {
                $columns[] = $definition['parent'];
                $values[] = '?';
                $bindings[] = $parentId;
            }

            foreach ($geometry as $column => [$sql, $geometryBindings]) {
                $columns[] = $column;
                $values[] = $sql;
                array_push($bindings, ...$geometryBindings);
            }

            DB::insert('INSERT INTO '.$table.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).')', $bindings);

            return 'created';
        }

        $sets = [];
        $bindings = [];

        if ($existing->name !== $name) {
            $sets[] = 'name = ?';
            $bindings[] = $name;
        }

        foreach ($geometry as $column => [$sql, $geometryBindings]) {
            if (! $existing->{'same_'.$column}) {
                $sets[] = "{$column} = {$sql}";
                array_push($bindings, ...$geometryBindings);
            }
        }

        if ($sets === []) {
            return 'unchanged';
        }

        $sets[] = 'updated_at = ?';
        $bindings[] = $now;
        $bindings[] = $existing->id;

        DB::update('UPDATE '.$table.' SET '.implode(', ', $sets).' WHERE id = ?', $bindings);

        return 'updated';
    }

    /** @param array<string, mixed> $row */
    private function resolveParent(string $level, array $row): ?int
    {
        if ($level === 'state') {
            return null;
        }

        $stateId = $this->cached('state:'.trim((string) $row['state_code']), fn () => DB::table('states')->where('code', trim((string) $row['state_code']))->value('id'));
        if ($stateId === null || $level === 'lga') {
            return $stateId;
        }

        $lgaCode = trim((string) $row['lga_code']);
        $lgaId = $this->cached("lga:{$stateId}:{$lgaCode}", fn () => DB::table('lgas')->where('state_id', $stateId)->where('code', $lgaCode)->value('id'));
        if ($lgaId === null || $level === 'ward') {
            return $lgaId;
        }

        $wardCode = trim((string) $row['ward_code']);

        return $this->cached("ward:{$lgaId}:{$wardCode}", fn () => DB::table('wards')->where('lga_id', $lgaId)->where('code', $wardCode)->value('id'));
    }

    private function cached(string $key, callable $lookup): ?int
    {
        if (! array_key_exists($key, $this->parentCache)) {
            $value = $lookup();
            $this->parentCache[$key] = $value === null ? null : (int) $value;
        }

        return $this->parentCache[$key];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: float, 1: float}|false|null [latitude, longitude], false if invalid, null if absent
     */
    private function pointFrom(array $row): array|false|null
    {
        if (($row['_geometry']['type'] ?? null) === 'Point') {
            $coordinates = $row['_geometry']['coordinates'] ?? [];
            $longitude = $coordinates[0] ?? null;
            $latitude = $coordinates[1] ?? null;
        } else {
            $latitude = $row['latitude'] ?? $row['lat'] ?? null;
            $longitude = $row['longitude'] ?? $row['lng'] ?? $row['lon'] ?? null;
        }

        $latitude = is_string($latitude) ? trim($latitude) : $latitude;
        $longitude = is_string($longitude) ? trim($longitude) : $longitude;

        if (($latitude === null || $latitude === '') && ($longitude === null || $longitude === '')) {
            return null;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            return false;
        }

        return [(float) $latitude, (float) $longitude];
    }

    /** @return \Generator<int, array<string, mixed>> keyed by line number */
    private function readCsv(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}");
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header === false || $header === [null]) {
                throw new InvalidArgumentException('The CSV file is empty.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(fn ($name) => strtolower(trim((string) $name)), $header);

            foreach (['code', 'name'] as $required) {
                if (! in_array($required, $header, true)) {
                    throw new InvalidArgumentException("The CSV file has no '{$required}' column.");
                }
            }

            $line = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }

                $row = [];
                foreach ($header as $index => $name) {
                    $row[$name] = $values[$index] ?? null;
                }

                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return \Generator<int, array<string, mixed>> keyed by feature number */
    private function readGeoJson(string $path): \Generator
    {
        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            throw new InvalidArgumentException('The GeoJSON file must be a FeatureCollection.');
        }

        foreach ($data['features'] as $index => $feature) {
            $row = array_change_key_case((array) ($feature['properties'] ?? []), CASE_LOWER);
            $row['_geometry'] = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;

            yield $index + 1 => $row;
        }
    }

    private function shortError(QueryException $e): string
    {
        $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $message)), 0, 160);
    }
}
