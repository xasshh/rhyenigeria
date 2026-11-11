<?php

namespace Tests\Feature;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;
use App\Services\Geo\GeoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeoImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/geo-import-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $name, string $contents): string
    {
        file_put_contents($path = $this->dir.'/'.$name, $contents);

        return $path;
    }

    private function sample(string $name): string
    {
        return base_path('database/data/samples/'.$name);
    }

    private function importSamples(): void
    {
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->sample('states.csv')])->assertSuccessful();
        $this->artisan('geo:import', ['level' => 'lga', 'file' => $this->sample('lgas.csv')])->assertSuccessful();
        $this->artisan('geo:import', ['level' => 'ward', 'file' => $this->sample('wards.geojson')])->assertSuccessful();
        $this->artisan('geo:import', ['level' => 'polling-unit', 'file' => $this->sample('polling_units.csv')])->assertSuccessful();
    }

    public function test_the_sample_files_import_with_parents_codes_and_geometry(): void
    {
        $this->importSamples();

        $this->assertSame([2, 3, 3, 4], [State::count(), Lga::count(), Ward::count(), PollingUnit::count()]);

        $ward = Ward::where('code', 'LA-IKJ-01')->sole();
        $this->assertSame('LA-IKJ', $ward->lga->code);
        $this->assertSame('LA', $ward->lga->state->code);

        $boundary = DB::selectOne('SELECT GeometryType(boundary) AS type, ST_SRID(boundary) AS srid, ST_IsValid(boundary) AS valid FROM wards WHERE id = ?', [$ward->id]);
        $this->assertSame(['MULTIPOLYGON', 4326, true], [$boundary->type, (int) $boundary->srid, (bool) $boundary->valid]);

        $unit = PollingUnit::where('code', 'LA-IKJ-01-001')->withCoordinates()->sole();
        $this->assertSame($ward->id, $unit->ward_id);
        $this->assertEqualsWithDelta([6.6005, 3.3401], [(float) $unit->latitude, (float) $unit->longitude], 0.0000001);
        $this->assertSame(4326, (int) DB::scalar('SELECT ST_SRID(location) FROM polling_units WHERE id = ?', [$unit->id]));

        // The imported point really is inside the imported ward polygon.
        $this->assertTrue((bool) DB::scalar('SELECT ST_Covers(w.boundary, p.location) FROM wards w, polling_units p WHERE w.id = ? AND p.id = ?', [$ward->id, $unit->id]));
    }

    public function test_spatial_indexes_exist_on_every_geometry_column(): void
    {
        $indexes = collect(DB::select("SELECT tablename, indexdef FROM pg_indexes WHERE schemaname = 'public' AND indexdef ILIKE '%USING gist%'"))
            ->map(fn ($row) => $row->tablename.':'.preg_replace('/.*\((\w+)\)$/', '$1', $row->indexdef))
            ->all();

        foreach (['states:boundary', 'lgas:boundary', 'wards:boundary', 'polling_units:boundary', 'polling_units:location', 'location_points:location', 'agent_positions:location', 'submissions:location'] as $expected) {
            $this->assertContains($expected, $indexes);
        }
    }

    public function test_running_the_same_files_again_changes_nothing(): void
    {
        $this->importSamples();
        $before = DB::table('polling_units')->orderBy('id')->get(['id', 'updated_at'])->toJson();

        $importer = app(GeoImporter::class);

        foreach ([['state', 'states.csv', 2], ['lga', 'lgas.csv', 3], ['ward', 'wards.geojson', 3], ['polling-unit', 'polling_units.csv', 4]] as [$level, $file, $rows]) {
            $result = $importer->import($level, $this->sample($file));

            $this->assertSame([0, 0, $rows, $rows, 0], [$result->created, $result->updated, $result->skipped(), $result->unchanged, $result->invalid], $file);
        }

        $this->assertSame([2, 3, 3, 4], [State::count(), Lga::count(), Ward::count(), PollingUnit::count()]);
        $this->assertSame($before, DB::table('polling_units')->orderBy('id')->get(['id', 'updated_at'])->toJson());
    }

    public function test_changed_rows_are_updated_and_new_rows_created(): void
    {
        $this->importSamples();

        $result = app(GeoImporter::class)->import('polling-unit', $this->file('pu.csv', implode("\n", [
            'state_code,lga_code,ward_code,code,name,latitude,longitude',
            'LA,LA-IKJ,LA-IKJ-01,LA-IKJ-01-001,Sample Primary School I,6.6005,3.3401',   // unchanged
            'LA,LA-IKJ,LA-IKJ-01,LA-IKJ-01-002,Renamed Town Hall,6.6052,3.3455',          // renamed
            'LA,LA-IKJ,LA-IKJ-02,LA-IKJ-02-001,Sample Market Square,6.5991,3.3603',       // moved
            'LA,LA-IKJ,LA-IKJ-02,LA-IKJ-02-002,Brand New Unit,6.6000,3.3650',             // new
        ])));

        $this->assertSame([1, 2, 1, 0], [$result->created, $result->updated, $result->unchanged, $result->invalid]);
        $this->assertSame('Renamed Town Hall', PollingUnit::where('code', 'LA-IKJ-01-002')->value('name'));
        $this->assertEqualsWithDelta(6.5991, (float) DB::scalar("SELECT ST_Y(location) FROM polling_units WHERE code = 'LA-IKJ-02-001'"), 0.0000001);
        $this->assertSame(5, PollingUnit::count());
    }

    public function test_a_file_without_geometry_does_not_erase_stored_geometry(): void
    {
        $this->importSamples();

        $result = app(GeoImporter::class)->import('ward', $this->file('wards.csv', "state_code,lga_code,code,name\nLA,LA-IKJ,LA-IKJ-01,Ward A Renamed\n"));

        $this->assertSame(1, $result->updated);
        $this->assertSame('Ward A Renamed', Ward::where('code', 'LA-IKJ-01')->value('name'));
        $this->assertTrue((bool) DB::scalar("SELECT boundary IS NOT NULL FROM wards WHERE code = 'LA-IKJ-01'"));
    }

    public function test_invalid_rows_are_skipped_and_reported_without_stopping_the_import(): void
    {
        $this->importSamples();

        $result = app(GeoImporter::class)->import('polling-unit', $this->file('pu.csv', implode("\n", [
            'state_code,lga_code,ward_code,code,name,latitude,longitude',
            'LA,LA-IKJ,LA-IKJ-01,,No Code,6.60,3.34',
            'LA,LA-IKJ,NO-SUCH-WARD,X-1,Orphan,6.60,3.34',
            'LA,LA-IKJ,LA-IKJ-01,X-2,Bad Coordinates,96.60,3.34',
            'LA,LA-IKJ,LA-IKJ-01,X-3,Half Coordinates,6.60,',
            '',
            'LA,LA-IKJ,LA-IKJ-01,X-4,Good One,6.6010,3.3410',
            'LA,LA-IKJ,LA-IKJ-01,X-5,No Location Yet,,',
        ])));

        $this->assertSame([2, 0, 4], [$result->created, $result->updated, $result->invalid]);
        $this->assertSame(4, $result->skipped());
        $this->assertStringContainsString('Row 2: missing code or name', $result->problems[0]);
        $this->assertStringContainsString('Row 3: parent not found', $result->problems[1]);
        $this->assertStringContainsString('Row 4: invalid latitude/longitude', $result->problems[2]);
        $this->assertTrue(PollingUnit::where('code', 'X-4')->exists());
        $this->assertFalse((bool) DB::scalar("SELECT location IS NOT NULL FROM polling_units WHERE code = 'X-5'"));
    }

    public function test_a_broken_polygon_is_skipped_and_the_rest_of_the_file_still_imports(): void
    {
        $this->importSamples();

        $feature = fn (string $code, array $geometry) => ['type' => 'Feature', 'properties' => ['STATE_CODE' => 'LA', 'LGA_CODE' => 'LA-ETI', 'CODE' => $code, 'NAME' => "Ward {$code}"], 'geometry' => $geometry];

        $result = app(GeoImporter::class)->import('ward', $this->file('wards.geojson', json_encode(['type' => 'FeatureCollection', 'features' => [
            $feature('E-1', ['type' => 'Polygon', 'coordinates' => [[[3.4, 6.4], [3.5, 6.4]]]]),            // not a closed ring
            $feature('E-2', ['type' => 'LineString', 'coordinates' => [[3.4, 6.4], [3.5, 6.4]]]),          // wrong type
            $feature('E-3', ['type' => 'Polygon', 'coordinates' => [[[3.4, 6.4], [3.5, 6.4], [3.5, 6.5], [3.4, 6.5], [3.4, 6.4]]]]),
        ]])));

        $this->assertSame([1, 2], [$result->created, $result->invalid]);
        $this->assertTrue(Ward::where('code', 'E-3')->exists());
        $this->assertFalse(Ward::where('code', 'E-1')->exists());
    }

    public function test_a_dry_run_reports_but_saves_nothing(): void
    {
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->sample('states.csv'), '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, State::count());
    }

    public function test_the_command_reports_created_updated_and_skipped_counts(): void
    {
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->sample('states.csv')])
            ->expectsTable(['Created', 'Updated', 'Skipped', 'of which unchanged', 'of which invalid', 'Rows read'], [[2, 0, 0, 0, 0, 2]])
            ->assertSuccessful();

        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->file('s.csv', "code,name\nLA,Lagos State\nFC,Federal Capital Territory\n,Nameless\n")])
            ->expectsTable(['Created', 'Updated', 'Skipped', 'of which unchanged', 'of which invalid', 'Rows read'], [[0, 1, 2, 1, 1, 3]])
            ->expectsOutputToContain('Row 4: missing code or name')
            ->assertSuccessful();
    }

    public function test_bad_arguments_fail_cleanly(): void
    {
        $this->artisan('geo:import', ['level' => 'country', 'file' => $this->sample('states.csv')])->assertFailed();
        $this->artisan('geo:import', ['level' => 'state', 'file' => '/no/such/file.csv'])->assertFailed();
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->file('s.txt', 'x')])->assertFailed();
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->file('s.csv', "title,label\nA,B\n")])->assertFailed();
        $this->artisan('geo:import', ['level' => 'state', 'file' => $this->file('s.geojson', '{"type":"Feature"}')])->assertFailed();
    }

    public function test_the_same_code_can_exist_under_different_parents(): void
    {
        $this->importSamples();

        $result = app(GeoImporter::class)->import('ward', $this->file('w.csv', "state_code,lga_code,code,name\nLA,LA-IKJ,01,Ward One\nLA,LA-ETI,01,Ward One\n"));

        $this->assertSame(2, $result->created);
        $this->assertSame(2, Ward::where('code', '01')->count());
    }
}
