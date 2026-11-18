<?php

namespace Tests\Feature;

use App\Models\Lga;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Group member lists (e.g. Team Kaka) are imported as phone-only members, sent a temporary password by SMS,
 * sign in with their phone, and finish their profile (own password, gender, age, consent) on first sign-in.
 */
class ImportedMembersTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    private Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();

        $state = State::factory()->create(['code' => 'KD', 'name' => 'Kaduna']);
        $this->lga = Lga::factory()->create(['state_id' => $state->id, 'name' => 'Kaduna North']);
        $this->csv = sys_get_temp_dir().'/group-'.uniqid().'.csv';
        file_put_contents($this->csv, implode("\n", [
            'Name of Group,,Team Kaka,,,',
            'Address,,"No. 5A Mambila Close, Malali Kaduna",,,',
            'State,,Kaduna State,,,',
            'LGA,,Kaduna North,,,',
            ',,,,,',
            'S/N,Phone,Member Name,Gender,LGA,Role',
            '1,9166828810,DAHIRU HAUWAU,,,Member ',
            '2,081 39040422,AHMAD  AMINA ,,,Member ',
            '3,081 2559025,HAMISU MARYAM,,,Member ',
            '4,08139040422,DUPLICATE AMINA,,,Member ',
            '5,080 22531137,SAIDU SHAFA`ATU,,,Member ',
            '9594,,,,,9639',
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob(preg_replace('/\.csv$/', '', $this->csv).'*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_imports_valid_unique_numbers_and_writes_the_rejects(): void
    {
        $this->artisan('members:import', ['csv' => $this->csv])->assertSuccessful();

        $this->assertSame(3, User::count());
        $user = User::where('phone', '+2349166828810')->firstOrFail();
        $this->assertSame('Dahiru Hauwau', $user->name);
        $this->assertNull($user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->isMember());
        $this->assertSame($this->lga->id, $user->lga_id);
        $this->assertSame('Team Kaka', $user->memberProfile->import_source);
        $this->assertNull($user->memberProfile->consent_accepted_at);
        $this->assertStringStartsWith('RHYE-KD-', $user->memberProfile->member_number);
        $this->assertSame('Ahmad Amina', User::where('phone', '+2348139040422')->value('name'));
        $this->assertSame("Saidu Shafa'atu", User::where('phone', '+2348022531137')->value('name'));

        $rejects = file_get_contents(preg_replace('/\.csv$/', '', $this->csv).' - rejects.csv');
        $this->assertStringContainsString('not a valid Nigerian mobile number', $rejects);
        $this->assertStringContainsString('same phone as row 2', $rejects);

        // Re-running skips everyone already imported.
        $this->artisan('members:import', ['csv' => $this->csv])->assertSuccessful();
        $this->assertSame(3, User::count());
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $this->artisan('members:import', ['csv' => $this->csv, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_credentials_are_sent_by_sms_and_only_once(): void
    {
        config(['services.bulksmsnigeria.token' => 'test-token', 'app.url' => 'https://rhye.ng']);
        Http::fake([
            '*/balance' => Http::response(['data' => ['status' => 'success'], 'balance' => ['total_balance' => 5000]]),
            '*/sms' => Http::response(['status' => 'success', 'code' => 'BSNG-0000', 'data' => ['message_id' => 'm1', 'cost' => 6.49]]),
        ]);
        $this->artisan('members:import', ['csv' => $this->csv])->assertSuccessful();

        $this->artisan('members:send-credentials', ['--source' => 'Team Kaka', '--pause' => 0])->assertSuccessful();

        $sent = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sms'));
        $this->assertCount(3, $sent);
        $request = $sent->first()[0];
        $this->assertSame('Bearer test-token', $request->header('Authorization')[0]);
        $this->assertSame('2349166828810', $request['to']);
        $this->assertLessThanOrEqual(160, mb_strlen($request['body']));
        $this->assertMatchesRegularExpression('/^Hi Dahiru, .*rhye\.ng\/login Phone: 09166828810 Password: ([a-z2-9]{8}) /', $request['body']);

        preg_match('/Password: ([a-z2-9]{8})/', $request['body'], $m);
        $user = User::where('phone', '+2349166828810')->first();
        $this->assertTrue(Hash::check($m[1], $user->password));
        $this->assertNotNull($user->memberProfile->credentials_sent_at);

        // Already sent: nobody is messaged again unless --resend or --phone is given.
        $this->artisan('members:send-credentials', ['--source' => 'Team Kaka', '--pause' => 0])->assertSuccessful();
        $this->assertCount(3, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sms')));
    }

    public function test_a_refused_sms_leaves_the_password_unchanged(): void
    {
        config(['services.bulksmsnigeria.token' => 'test-token']);
        Http::fake([
            '*/balance' => Http::response([], 500),
            '*/sms' => Http::response(['status' => 'error', 'code' => 'BSNG-3000', 'message' => 'Insufficient balance'], 402),
        ]);
        $this->artisan('members:import', ['csv' => $this->csv])->assertSuccessful();
        $before = User::where('phone', '+2349166828810')->value('password');

        $this->artisan('members:send-credentials', ['--source' => 'Team Kaka', '--pause' => 0])->assertFailed();

        // Out of credit stops after the first failure.
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sms')));
        $this->assertSame($before, User::where('phone', '+2349166828810')->value('password'));
        $this->assertSame(0, MemberProfile::whereNotNull('credentials_sent_at')->count());
    }

    public function test_members_sign_in_with_their_phone_and_must_finish_setup_first(): void
    {
        $this->artisan('members:import', ['csv' => $this->csv])->assertSuccessful();
        $user = User::where('phone', '+2349166828810')->first();
        $user->forceFill(['password' => Hash::make('temp-pass')])->save();

        $this->post('/login', ['email' => '0916 682 8810', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => '0916 682 8810', 'password' => 'temp-pass'])->assertRedirect(route('membership.home'));

        $this->get(route('membership.home'))->assertRedirect(route('membership.setup'));
        $this->get(route('membership.card'))->assertRedirect(route('membership.setup'));
        $this->get(route('membership.setup'))->assertOk()->assertSee('Finish setting up')->assertSee('Team Kaka');

        $this->put(route('membership.setup.complete'), ['name' => 'Hauwau Dahiru', 'gender' => 'female', 'age_range' => '18-24'])
            ->assertSessionHasErrors(['password', 'consent']);

        $this->put(route('membership.setup.complete'), [
            'name' => 'Hauwau Dahiru', 'gender' => 'female', 'age_range' => '18-24',
            'password' => 'my-own-pass', 'password_confirmation' => 'my-own-pass', 'consent' => '1',
        ])->assertRedirect(route('membership.welcome'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass', $user->password));
        $this->assertNotNull($user->memberProfile->consent_accepted_at);
        $this->assertSame('Hauwau Dahiru', $user->name);
        $this->get(route('membership.home'))->assertOk();
        $this->get(route('membership.setup'))->assertRedirect(route('membership.home'));
    }

    public function test_members_who_joined_on_the_website_skip_setup(): void
    {
        $profile = MemberProfile::factory()->create();

        $this->actingAs($profile->user)->get(route('membership.home'))->assertOk();
    }
}
