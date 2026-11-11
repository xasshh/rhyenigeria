<?php

namespace Tests;

use App\Events\AgentPositionUpdated;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Shift;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never talk to a real Reverb server from tests.
        Event::fake([AgentPositionUpdated::class]);
    }

    /**
     * Two states. State "one" has two LGAs; its first LGA has two adjoining
     * square wards (A to the west, B to the east) with a polling unit in each.
     *
     * @return array{state: State, otherState: State, lga: Lga, otherLga: Lga, wardA: Ward, wardB: Ward, farWard: Ward, unitA: PollingUnit, unitB: PollingUnit}
     */
    protected function geography(): array
    {
        $state = State::factory()->create();
        $otherState = State::factory()->create();
        $lga = Lga::factory()->for($state)->create();
        $otherLga = Lga::factory()->for($state)->create();
        $wardA = Ward::factory()->for($lga)->create();
        $wardB = Ward::factory()->for($lga)->create();
        $farWard = Ward::factory()->for(Lga::factory()->for($otherState))->create();

        $this->setBoundary($wardA, 3.33, 6.59, 3.35, 6.61);
        $this->setBoundary($wardB, 3.35, 6.59, 3.37, 6.61);

        $unitA = PollingUnit::factory()->for($wardA)->at(6.6005, 3.3401)->create();
        $unitB = PollingUnit::factory()->for($wardB)->at(6.5990, 3.3602)->create();

        return compact('state', 'otherState', 'lga', 'otherLga', 'wardA', 'wardB', 'farWard', 'unitA', 'unitB');
    }

    protected function setBoundary(Ward $ward, float $west, float $south, float $east, float $north): void
    {
        DB::update(
            'UPDATE wards SET boundary = ST_Multi(ST_MakeEnvelope(?, ?, ?, ?, 4326)) WHERE id = ?',
            [$west, $south, $east, $north, $ward->id],
        );
    }

    /** A consenting field agent, optionally with a shift that started an hour ago. */
    protected function agent(?object $area = null, bool $onShift = true): User
    {
        $agent = User::factory()->agent($area)->consented()->create();

        if ($onShift) {
            Shift::factory()->for($agent)->create(['started_at' => now()->subHour()]);
        }

        return $agent;
    }

    /** @return array{uuid: string, latitude: float, longitude: float, accuracy: float, recorded_at: string} */
    protected function point(float $latitude, float $longitude, ?CarbonInterface $at = null, ?string $uuid = null): array
    {
        return [
            'uuid' => $uuid ?? (string) Str::uuid(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => 8.5,
            'recorded_at' => ($at ?? now())->toIso8601String(),
        ];
    }
}
