<?php

namespace App\Http\Controllers\Web;

use App\Enums\IncidentCategory;
use App\Enums\IncidentUrgency;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Models\PollingUnit;
use App\Support\WebImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Members and agents report incidents (at a polling unit, a rally...) to the command centre,
 * with photos and a description, and send updates while it is still going on.
 */
class IncidentController extends Controller
{
    private const PHOTO_RULES = ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'];

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('membership.incidents', [
            'user' => $user->loadMissing('ward', 'lga'),
            'incidents' => $user->incidents()->with('photos', 'updates.photos', 'pollingUnit', 'ward', 'lga')->latest()->limit(20)->get(),
            'categories' => IncidentCategory::cases(),
            'urgencies' => IncidentUrgency::cases(),
            'pollingUnits' => $user->ward_id ? PollingUnit::where('ward_id', $user->ward_id)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'myUnitId' => $user->memberProfile?->polling_unit_id,
            'myUnitName' => $user->memberProfile?->polling_unit_name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'category' => ['required', new Enum(IncidentCategory::class)],
            'urgency' => ['required', new Enum(IncidentUrgency::class)],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'polling_unit_id' => ['nullable', 'integer', Rule::exists('polling_units', 'id')->where('ward_id', (int) $user->ward_id)],
            'polling_unit_name' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photos' => ['nullable', 'array', 'max:'.Incident::MAX_PHOTOS],
            'photos.*' => self::PHOTO_RULES,
        ], [
            'description.min' => 'Tell us a little more about what is happening.',
            'photos.max' => 'Send up to '.Incident::MAX_PHOTOS.' photos.',
            'photos.*.max' => 'Each photo must be under 12 MB.',
        ]);

        $incident = new Incident([
            ...collect($data)->only(['category', 'urgency', 'description', 'polling_unit_id'])->all(),
            'polling_unit_name' => empty($data['polling_unit_id']) ? (trim((string) preg_replace('/\s+/', ' ', (string) ($data['polling_unit_name'] ?? ''))) ?: null) : null,
            'state_id' => $user->state_id,
            'lga_id' => $user->lga_id,
            'ward_id' => $user->ward_id,
            'latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 6) : null,
            'longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 6) : null,
            'accuracy' => isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null,
        ]);
        $incident->user_id = $user->id;
        $incident->save();
        $this->savePhotos($incident, $request->file('photos', []));

        return redirect()->route('membership.incidents')->with('status', 'Your report has reached the command centre. Add an update below if anything changes.');
    }

    /** More news on the same incident, while it is still open. */
    public function storeUpdate(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->user_id === $request->user()->id, 404);
        abort_unless($incident->isOpen(), 409);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.Incident::MAX_PHOTOS],
            'photos.*' => self::PHOTO_RULES,
        ]);

        $update = $incident->updates()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $this->savePhotos($incident, $request->file('photos', []), $update->id);
        $incident->touch();

        return back()->with('status', 'Update sent to the command centre.');
    }

    /** A photo: the person who sent it, or a super admin. */
    public function photo(Request $request, IncidentPhoto $photo): StreamedResponse
    {
        $viewer = $request->user();
        $incident = $photo->incident;
        abort_unless(
            $viewer->isSuperAdmin() || $incident->user_id === $viewer->id || ($viewer->isStateCoordinator() && $viewer->state_id === $incident->state_id),
            404,
        );
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** @param  array<int, UploadedFile>  $files */
    private function savePhotos(Incident $incident, array $files, ?int $updateId = null): void
    {
        foreach ($files as $file) {
            $incident->photos()->create([
                'incident_update_id' => $updateId,
                'path' => WebImage::storePrivate($file, 'incidents/'.$incident->id),
            ]);
        }
    }
}
