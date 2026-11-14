<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgeRange;
use App\Enums\CanvasserRole;
use App\Enums\CanvasserStatus;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\EmpowermentReport;
use App\Models\Event;
use App\Models\NewsPost;
use App\Models\PollingUnit;
use App\Models\Setting;
use App\Models\User;
use App\Support\NigerianPhone;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The member dashboard at /membership. */
class MembershipController extends Controller
{
    public function welcome(Request $request): View
    {
        return view('membership.welcome', $this->cardData($request->user()));
    }

    public function home(Request $request): View
    {
        $user = $request->user()->load('memberProfile', 'state');

        return view('membership.home', [
            'user' => $user,
            'profile' => $user->memberProfile,
            'electionDate' => Setting::electionDate(),
            'electionLabel' => Setting::electionLabel(),
            'nextEvent' => Event::query()->forState($user->state_id)->upcoming()->with('state')->first(),
            'news' => NewsPost::query()->forMember($user->state_id, $user->ward_id)->with('state', 'ward', 'photos')->latest('published_at')->limit(5)->get(),
            'canvasserRoles' => CanvasserRole::cases(),
        ]);
    }

    public function card(Request $request): View
    {
        $user = $request->user();
        $profile = $user->memberProfile;

        return view('membership.card', $this->cardData($user) + [
            'genders' => Gender::cases(),
            'ageRanges' => AgeRange::cases(),
            'pollingUnits' => $user->ward_id
                ? PollingUnit::where('ward_id', $user->ward_id)->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'profile' => $profile,
        ]);
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'gender' => ['required', new Enum(Gender::class)],
            'age_range' => ['required', new Enum(AgeRange::class)],
            'occupation' => ['nullable', 'string', 'max:120'],
            'polling_unit_id' => ['nullable', 'integer', Rule::exists('polling_units', 'id')->where('ward_id', (int) $user->ward_id)],
        ]);

        $user->update(['name' => preg_replace('/\s+/', ' ', trim($data['name']))]);
        $profile = $user->memberProfile;
        $profile->gender = $data['gender'];
        $profile->age_range = $data['age_range'];
        $profile->occupation = $data['occupation'] ?? null;
        $profile->polling_unit_id = $data['polling_unit_id'] ?? null;
        $profile->save();

        return back()->with('status', 'Your details have been saved.');
    }

    public function updateIdentity(Request $request): RedirectResponse
    {
        $request->merge([
            'vin' => strtoupper((string) preg_replace('/[\s-]+/', '', (string) $request->input('vin'))) ?: null,
            'nin' => preg_replace('/\s+/', '', (string) $request->input('nin')) ?: null,
        ]);
        $data = $request->validate([
            'has_pvc' => ['required', 'boolean'],
            'vin' => ['nullable', 'string', 'regex:/^[A-Z0-9]{19}$/'],
            'nin' => ['nullable', 'string', 'regex:/^\d{11}$/'],
        ], [
            'vin.regex' => 'The VIN is the 19 letters and numbers printed on your PVC.',
            'nin.regex' => 'Your NIN is 11 digits.',
        ]);

        $profile = $request->user()->memberProfile;
        $profile->has_pvc = (bool) $data['has_pvc'];
        // Blank fields keep what is stored; they are never shown back in full.
        if ($data['vin']) {
            $profile->vin = $data['vin'];
            $profile->has_pvc = true;
        }
        if ($data['nin']) {
            $profile->nin = $data['nin'];
        }
        $profile->save();

        return back()->with('status', 'Your voter details have been saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Your password has been changed.');
    }

    public function applyCanvasser(Request $request): RedirectResponse
    {
        $profile = $request->user()->memberProfile;
        abort_if(in_array($profile->canvasser_status, [CanvasserStatus::Pending, CanvasserStatus::Approved], true), 409);

        if (filled($request->input('whatsapp_phone'))) {
            $request->merge(['whatsapp_phone' => NigerianPhone::normalise($request->input('whatsapp_phone')) ?? $request->input('whatsapp_phone')]);
        }
        $data = $request->validate([
            'canvasser_roles' => ['required', 'array', 'min:1'],
            'canvasser_roles.*' => ['distinct', new Enum(CanvasserRole::class)],
            'whatsapp_phone' => ['nullable', 'regex:/^\+234[789][01]\d{8}$/'],
        ], ['canvasser_roles.required' => 'Choose at least one way you would like to help.', 'whatsapp_phone.regex' => 'Enter a Nigerian mobile number.']);

        $profile->canvasser_status = CanvasserStatus::Pending;
        $profile->canvasser_roles = array_values($data['canvasser_roles']);
        $profile->whatsapp_phone = $data['whatsapp_phone'] ?? $request->user()->phone;
        $profile->canvasser_applied_at = now();
        $profile->canvasser_reviewed_by = null;
        $profile->canvasser_reviewed_at = null;
        $profile->canvasser_review_note = null;
        $profile->save();

        return back()->with('status', 'Thank you! Your state coordinator will review your application.');
    }

    public function events(Request $request): View
    {
        $user = $request->user();
        $mine = $request->query('scope') === 'state';

        $events = Event::query()
            ->when($mine, fn ($q) => $q->where('state_id', $user->state_id), fn ($q) => $q->forState($user->state_id))
            ->upcoming()->with('state')->limit(30)->get();

        $news = NewsPost::query()->forMember($user->state_id, $user->ward_id)
            ->when($mine, fn ($q) => $q->whereNotNull('state_id'))
            ->with('state', 'ward', 'photos')->latest('published_at')->paginate(15)->withQueryString();

        return view('membership.events', compact('events', 'news', 'mine'));
    }

    public function empowerment(Request $request): View
    {
        $user = $request->user();

        return view('membership.empowerment', [
            'profile' => $user->memberProfile,
            'reports' => $user->empowermentReports()->latest()->get(),
        ]);
    }

    public function storeEmpowerment(Request $request): RedirectResponse
    {
        $profile = $request->user()->memberProfile;

        if (! $request->boolean('benefited')) {
            $request->validate(['benefited' => ['required', 'boolean']]);
            $profile->empowerment_benefited = false;
            $profile->save();

            return back()->with('status', 'Thanks for letting us know.');
        }

        $data = $request->validate([
            'programme' => ['required', 'string', 'max:255'],
            'received_on' => ['nullable', 'date', 'before_or_equal:today', 'after:2015-01-01'],
            'what_received' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('membership.empowerment_photo_max_kb')],
        ]);

        $report = new EmpowermentReport(collect($data)->only(['programme', 'received_on', 'what_received'])->all());
        $report->user_id = $request->user()->id;
        if ($request->hasFile('photo')) {
            // Private disk: only reachable through the scoped photo route.
            $report->photo_path = $request->file('photo')->store('empowerment/'.$request->user()->id, 'local');
        }
        $report->save();

        $profile->empowerment_benefited = true;
        $profile->save();

        return back()->with('status', 'Thank you. Your empowerment report has been saved.');
    }

    public function dp(Request $request): View
    {
        return view('membership.dp', ['user' => $request->user()->load('state', 'lga')]);
    }

    /** @return array<string, mixed> */
    private function cardData(User $user): array
    {
        $user->loadMissing('memberProfile', 'state', 'lga', 'ward');
        $profile = $user->memberProfile;
        $verifyUrl = route('verify', $profile->verify_token);
        $inviteUrl = route('join', ['ref' => $profile->referral_code]);
        $message = 'I just joined the Renewed Hope Youth Engagement movement. '.config('membership.slogan').' Join me: '.$inviteUrl;

        return [
            'user' => $user,
            'profile' => $profile,
            'qrSvg' => Qr::svg($verifyUrl),
            'verifyUrl' => $verifyUrl,
            'inviteUrl' => $inviteUrl,
            'shareMessage' => $message,
            'shareLinks' => [
                'WhatsApp' => 'https://wa.me/?text='.rawurlencode($message),
                'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($inviteUrl).'&quote='.rawurlencode($message),
                'X' => 'https://x.com/intent/post?text='.rawurlencode($message),
            ],
            'whatsappGroup' => $user->state?->whatsapp_group_url,
        ];
    }
}
