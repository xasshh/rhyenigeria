<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\State;
use App\Support\WebImage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'electionDate' => Setting::get(Setting::ELECTION_DATE) ? Setting::electionDate()->setTimezone(config('membership.timezone')) : null,
            'electionLabel' => Setting::get(Setting::ELECTION_LABEL),
            'states' => State::orderBy('name')->get(['id', 'name', 'whatsapp_group_url', 'coordinator_name', 'coordinator_photo_path']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'election_date' => ['nullable', 'date'],
            'election_label' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['array'],
            'whatsapp.*' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(chat\.whatsapp\.com|wa\.me|whatsapp\.com\/channel)\//'],
            'coordinator_name' => ['array'],
            'coordinator_name.*' => ['nullable', 'string', 'max:120'],
            'coordinator_photo' => ['array'],
            'coordinator_photo.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_photo' => ['array'],
        ], [
            'whatsapp.*.regex' => 'Use a WhatsApp invite link (https://chat.whatsapp.com/…).',
            'coordinator_photo.*.image' => 'Upload a JPEG, PNG or WebP photo.',
        ]);

        Setting::put(Setting::ELECTION_DATE, empty($data['election_date']) ? null
            : CarbonImmutable::parse($data['election_date'], config('membership.timezone'))->utc()->toIso8601String());
        Setting::put(Setting::ELECTION_LABEL, $data['election_label'] ?? null);

        $ids = array_map('intval', array_keys(($data['whatsapp'] ?? []) + ($data['coordinator_name'] ?? []) + $request->file('coordinator_photo', []) + ($data['remove_photo'] ?? [])));
        foreach (State::whereIn('id', $ids)->get() as $state) {
            if (array_key_exists($state->id, $data['whatsapp'] ?? [])) {
                $state->whatsapp_group_url = $data['whatsapp'][$state->id] ?: null;
            }
            if (array_key_exists($state->id, $data['coordinator_name'] ?? [])) {
                $state->coordinator_name = $data['coordinator_name'][$state->id] ?: null;
            }
            $photo = $request->file('coordinator_photo.'.$state->id);
            if ($photo || $request->boolean('remove_photo.'.$state->id)) {
                Storage::disk('public')->delete(array_filter([$state->coordinator_photo_path]));
                $state->coordinator_photo_path = null;
            }
            if ($photo) {
                // Only the 600 px WebP copy is kept; the original upload is not needed.
                [$original, $state->coordinator_photo_path] = WebImage::storeWithWebp($photo, 'coordinators', 600);
                Storage::disk('public')->delete($original);
            }
            $state->save();
        }

        return back()->with('status', 'Settings saved.');
    }
}
