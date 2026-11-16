<?php

namespace Tests\Feature;

use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The coordinators' Google Form export fills in each state's coordinator, photo and WhatsApp link. */
class ImportStateCoordinatorsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/coordinators-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function photo(string $name): void
    {
        $image = UploadedFile::fake()->image('p.jpg', 800, 800);
        file_put_contents($this->dir.'/'.$name, $image->getContent());
    }

    public function test_it_imports_names_photos_and_valid_links(): void
    {
        Storage::fake('public');
        $katsina = State::factory()->create(['name' => 'Katsina', 'code' => 'KT']);
        $kebbi = State::factory()->create(['name' => 'Kebbi', 'code' => 'KE', 'whatsapp_group_url' => 'https://chat.whatsapp.com/OldKebbi']);
        $ebonyi = State::factory()->create(['name' => 'Ebonyi', 'code' => 'EB']);

        $this->photo('IMG_6845 - Khalil Nur Khalil.jpeg');
        $this->photo('PASSPORT  - Jedi Food Company.jpg');
        file_put_contents($csv = $this->dir.'/form.csv', implode("\n", [
            '"Timestamp","Full name","State","Email address","Phone number","Passport photograph","WhatsApp group link for your State Volunteer Group"',
            '"x","Khalil Nur Khalil","Katsina","a@b.c","080","drive","https://chat.whatsapp.com/Katsina123"',
            '"x","Bashar Aliyu Buhari","Kebbi","a@b.c","080","drive","Federal Capital Territory"',
            '"x","MICHAEL NWITE ","Ebonyi","a@b.c","080","drive","https://chat.whatsapp.com/Ebonyi123 "',
        ]));

        $this->artisan('coordinators:import', [
            'csv' => $csv,
            '--photos' => $this->dir,
            '--photo' => ['Ebonyi=PASSPORT  - Jedi Food Company.jpg'],
        ])->assertSuccessful();

        $katsina->refresh();
        $this->assertSame('Khalil Nur Khalil', $katsina->coordinator_name);
        $this->assertSame('https://chat.whatsapp.com/Katsina123', $katsina->whatsapp_group_url);
        Storage::disk('public')->assertExists($katsina->coordinator_photo_path);

        $kebbi->refresh();
        $this->assertSame('Bashar Aliyu Buhari', $kebbi->coordinator_name);
        $this->assertSame('https://chat.whatsapp.com/OldKebbi', $kebbi->whatsapp_group_url, 'An invalid link keeps the existing one');
        $this->assertNull($kebbi->coordinator_photo_path);

        $ebonyi->refresh();
        $this->assertSame('Michael Nwite', $ebonyi->coordinator_name);
        Storage::disk('public')->assertExists($ebonyi->coordinator_photo_path);
        $this->assertCount(2, Storage::disk('public')->allFiles('coordinators'), 'Only the WebP copies are kept');
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $state = State::factory()->create(['name' => 'Benue', 'code' => 'BE']);
        file_put_contents($csv = $this->dir.'/form.csv', "\"Full name\",\"State\",\"WhatsApp group link for your State Volunteer Group\"\n\"Fidelis Unongo\",\"Benue\",\"https://chat.whatsapp.com/Benue\"\n");

        $this->artisan('coordinators:import', ['csv' => $csv, '--dry-run' => true])->assertSuccessful();

        $this->assertNull($state->refresh()->coordinator_name);
    }
}
