<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgeRange;
use App\Enums\CanvasserRole;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Membership\JoinForm;
use App\Services\Membership\MemberRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JoinController extends Controller
{
    /** The chooser (member or canvasser), or the wizard once one is picked. */
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect($request->user()->homeRoute());
        }

        $ref = strtoupper((string) preg_replace('/[^A-Za-z0-9-]/', '', (string) $request->query('ref')));
        // Sites without agent sign-up go straight to the member form.
        $as = site_feature('agent_signup') ? $request->query('as') : 'member';
        // ?state=LA (from the homepage map) pre-selects the state; a single-state site always uses its own.
        $stateCode = site_state_code() ?? ($request->filled('state') ? strtoupper((string) $request->query('state')) : null);
        $preselected = $stateCode ? State::query()->where('code', $stateCode)->first(['id', 'code']) : null;

        if (! in_array($as, ['member', 'canvasser'], true)) {
            return view('join.choose', ['ref' => $ref, 'stateCode' => $preselected?->code]);
        }

        return view('join.wizard', [
            'canvasser' => $as === 'canvasser',
            'ref' => $ref,
            'preselectedStateId' => $preselected?->id,
            'states' => State::query()->when(site_state_code(), fn ($q, $code) => $q->where('code', $code))->orderBy('name')->get(['id', 'name']),
            'genders' => Gender::cases(),
            'ageRanges' => AgeRange::cases(),
            'canvasserRoles' => CanvasserRole::cases(),
        ]);
    }

    /** Check one step before the wizard moves on. */
    public function check(Request $request, string $step): JsonResponse
    {
        abort_unless(in_array($step, JoinForm::STEPS, true), 404);

        JoinForm::validate($request->all(), [$step], $request->boolean('canvasser'));

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, MemberRegistrar $registrar): JsonResponse
    {
        $request->validate(['as' => ['required', Rule::in(['member', 'canvasser'])]]);
        $canvasser = $request->input('as') === 'canvasser' && site_feature('agent_signup');

        $data = JoinForm::validate($request->all(), JoinForm::STEPS, $canvasser);
        $user = $registrar->register($data, $canvasser);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('membership.welcome')], 201);
    }
}
