<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Canvassers on shift sign up people they meet (often without a smartphone) from the field page. */
class FieldRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    private function canvasser(object $area): User
    {
        $user = User::factory()->member($area)->create(['name' => 'Ada Canvasser']);
        MemberProfile::factory()->for($user)->state(['canvasser_status' => CanvasserStatus::Approved])->create();

        return $user;
    }

    private function onShift(User $canvasser): void
    {
        $this->actingAs($canvasser)->post('/membership/field/consent', ['accepted' => 1]);
        $this->actingAs($canvasser)->postJson('/membership/field/shift')->assertCreated();
    }

    /** @return array<string, mixed> */
    private function person(array $geo, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Musa Bello', 'phone' => '0806 555 1234', 'gender' => 'male', 'age_range' => '35-44',
            'lga_id' => $geo['lga']->id, 'ward_id' => $geo['wardB']->id, 'polling_unit_id' => 'other', 'polling_unit_name' => 'Kasuwa Primary School',
            'has_pvc' => true, 'consent' => true, 'latitude' => 6.6, 'longitude' => 3.36,
        ], $overrides);
    }

    public function test_a_canvasser_on_shift_registers_someone_without_leaving_the_field_page(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->onShift($ada);

        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo))
            ->assertCreated()->assertJsonPath('member.name', 'Musa Bello');

        $musa = User::where('phone', '+2348065551234')->firstOrFail();
        $this->assertSame(Role::Member, $musa->role);
        $this->assertNull($musa->email);
        $this->assertTrue($musa->must_change_password);
        $this->assertSame($geo['wardB']->id, $musa->ward_id);

        $profile = $musa->memberProfile;
        $this->assertSame($ada->id, $profile->registered_by_user_id);
        $this->assertSame($ada->id, $profile->referred_by_user_id);
        $this->assertNotNull($profile->consent_accepted_at);
        $this->assertSame('Kasuwa Primary School', $profile->polling_unit_name);
        $this->assertNotNull($profile->signup_lat);

        $this->actingAs($ada)->get('/membership/field')->assertOk()->assertSee('Register someone');
    }

    public function test_registering_needs_a_shift_consent_and_an_unused_phone(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);

        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo))->assertStatus(409);

        $this->onShift($ada);
        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo, ['consent' => false]))->assertJsonValidationErrors('consent');
        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo, ['phone' => $ada->phone]))->assertJsonValidationErrors('phone');
        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo, ['lga_id' => $geo['farWard']->lga_id, 'ward_id' => $geo['farWard']->id]))->assertJsonValidationErrors('lga_id');

        $this->assertSame(0, MemberProfile::whereNotNull('registered_by_user_id')->count());
    }

    public function test_only_approved_canvassers_can_register_people(): void
    {
        $geo = $this->geography();
        $member = User::factory()->member($geo['wardA'])->create();
        MemberProfile::factory()->for($member)->create();

        $this->actingAs($member)->postJson('/membership/field/members', $this->person($geo))->assertForbidden();
    }

    public function test_field_registrations_are_rate_limited_per_canvasser(): void
    {
        config(['membership.field_registrations_per_hour' => 1]);
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->onShift($ada);

        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo))->assertCreated();
        $this->actingAs($ada)->postJson('/membership/field/members', $this->person($geo, ['phone' => '08065551235']))->assertStatus(429);
    }
}
