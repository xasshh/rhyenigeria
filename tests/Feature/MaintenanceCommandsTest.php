<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AgentPosition;
use App\Models\LocationPoint;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function agentWithHistory(): User
    {
        $agent = $this->agent();
        Shift::query()->update(['started_at' => now()->subDays(200)]);
        Sanctum::actingAs($agent);

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.60, 3.34, now()->subDays(120)),
            $this->point(6.61, 3.34, now()->subDays(91)),
            $this->point(6.62, 3.34, now()->subDays(89)),
            $this->point(6.63, 3.34, now()->subDays(10)),
            $this->point(6.64, 3.34, now()->subHour()),
        ]])->assertOk()->assertJson(['stored' => 5]);

        return $agent;
    }

    public function test_prune_deletes_points_older_than_the_configured_retention(): void
    {
        $this->agentWithHistory();

        $this->artisan('tracking:prune')->expectsOutputToContain('Deleted 2 location point(s)')->assertSuccessful();

        $this->assertSame(3, LocationPoint::count());
        $this->assertSame(0, LocationPoint::where('recorded_at', '<', now()->subDays(90))->count());
        $this->assertSame(1, AgentPosition::count(), 'The latest position is recent, so it stays.');
    }

    public function test_the_retention_period_is_configurable(): void
    {
        $this->agentWithHistory();

        config(['tracking.retention_days' => 100]);
        $this->artisan('tracking:prune')->assertSuccessful();
        $this->assertSame(4, LocationPoint::count());

        $this->artisan('tracking:prune', ['--days' => 5, '--dry-run' => true])->expectsOutputToContain('3 location point(s)')->assertSuccessful();
        $this->assertSame(4, LocationPoint::count());

        $this->artisan('tracking:prune', ['--days' => 5])->assertSuccessful();
        $this->assertSame(1, LocationPoint::count());

        $this->artisan('tracking:prune', ['--days' => 0])->assertFailed();
        $this->assertSame(1, LocationPoint::count());
    }

    public function test_a_stale_latest_position_is_pruned_with_its_history(): void
    {
        $agent = $this->agent();
        Shift::query()->update(['started_at' => now()->subDays(200)]);
        Sanctum::actingAs($agent);
        $this->postJson('/api/locations', ['points' => [$this->point(6.60, 3.34, now()->subDays(120))]])->assertOk();

        $this->artisan('tracking:prune')->assertSuccessful();

        $this->assertSame(0, LocationPoint::count());
        $this->assertSame(0, AgentPosition::count());
    }

    public function test_the_prune_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'tracking:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('15 3 * * *', $events->first()->expression);
    }

    public function test_the_super_admin_seeder_creates_the_account_from_the_environment(): void
    {
        putenv('SUPER_ADMIN_EMAIL=Boss@Example.com');
        putenv('SUPER_ADMIN_PASSWORD=a-very-long-secret');
        $_ENV['SUPER_ADMIN_EMAIL'] = $_SERVER['SUPER_ADMIN_EMAIL'] = 'Boss@Example.com';
        $_ENV['SUPER_ADMIN_PASSWORD'] = $_SERVER['SUPER_ADMIN_PASSWORD'] = 'a-very-long-secret';

        try {
            $this->seed(SuperAdminSeeder::class);

            $admin = User::sole();
            $this->assertSame('boss@example.com', $admin->email);
            $this->assertSame(Role::SuperAdmin, $admin->role);
            $this->assertTrue(Hash::check('a-very-long-secret', $admin->password));

            // Re-running neither duplicates the account nor changes its password.
            $_ENV['SUPER_ADMIN_PASSWORD'] = $_SERVER['SUPER_ADMIN_PASSWORD'] = 'a-different-secret-entirely';
            putenv('SUPER_ADMIN_PASSWORD=a-different-secret-entirely');
            $this->seed(SuperAdminSeeder::class);

            $this->assertSame(1, User::count());
            $this->assertTrue(Hash::check('a-very-long-secret', User::sole()->password));
        } finally {
            foreach (['SUPER_ADMIN_EMAIL', 'SUPER_ADMIN_PASSWORD'] as $key) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        }
    }

    public function test_the_default_seeder_creates_no_accounts(): void
    {
        $this->seed();

        $this->assertSame(0, User::count());
    }
}
