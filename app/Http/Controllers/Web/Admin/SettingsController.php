<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\State;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'electionDate' => Setting::get(Setting::ELECTION_DATE) ? Setting::electionDate()->setTimezone(config('membership.timezone')) : null,
            'electionLabel' => Setting::get(Setting::ELECTION_LABEL),
            'states' => State::orderBy('name')->get(['id', 'name', 'whatsapp_group_url']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'election_date' => ['nullable', 'date'],
            'election_label' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['array'],
            'whatsapp.*' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(chat\.whatsapp\.com|wa\.me|whatsapp\.com\/channel)\//'],
        ], ['whatsapp.*.regex' => 'Use a WhatsApp invite link (https://chat.whatsapp.com/…).']);

        Setting::put(Setting::ELECTION_DATE, empty($data['election_date']) ? null
            : CarbonImmutable::parse($data['election_date'], config('membership.timezone'))->utc()->toIso8601String());
        Setting::put(Setting::ELECTION_LABEL, $data['election_label'] ?? null);

        $ids = array_map('intval', array_keys($data['whatsapp'] ?? []));
        foreach (State::whereIn('id', $ids)->get() as $state) {
            $state->whatsapp_group_url = $data['whatsapp'][$state->id] ?: null;
            $state->save();
        }

        return back()->with('status', 'Settings saved.');
    }
}
