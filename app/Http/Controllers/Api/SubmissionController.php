<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\PollingUnit;
use App\Models\Submission;
use App\Models\SubmissionPhoto;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate($this->filterRules() + ['per_page' => ['nullable', 'integer', 'between:1,200']]);

        $page = $this->filtered($request, $data)
            ->withCoordinates()
            ->with(['user:id,name', 'form:id,key,name', 'pollingUnit:id,code,name', 'photos'])
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => collect($page->items())->map(fn (Submission $s) => $this->present($s))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Submission $submission): JsonResponse
    {
        $this->authorizeView($request, $submission);

        return response()->json(['submission' => $this->present($this->reload($submission))]);
    }

    /**
     * Create a submission. The client-generated UUID makes this safe to retry:
     * sending the same UUID again returns the record that was already stored.
     */
    public function store(Request $request): JsonResponse
    {
        // Multipart requests (used when photos are attached) cannot carry
        // nested JSON, so `answers` may arrive as a JSON string.
        if (is_string($request->input('answers'))) {
            $decoded = json_decode($request->input('answers'), true);
            $request->merge(['answers' => is_array($decoded) ? $decoded : null]);
        }

        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'form_id' => ['nullable', 'integer', 'exists:forms,id'],
            'form_key' => ['nullable', 'string', 'exists:forms,key'],
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'collected_at' => ['required', 'date'],
            'answers' => ['present', 'array'],
            'photos' => ['nullable', 'array', 'max:'.(int) config('fielddata.max_photos')],
            'photos.*' => $this->photoRules(),
        ]);

        $uuid = strtolower($data['uuid']);

        if ($existing = Submission::where('uuid', $uuid)->first()) {
            abort_unless($existing->user_id === $request->user()->id, 409, 'This UUID has already been used.');

            return response()->json(['submission' => $this->present($this->reload($existing)), 'duplicate' => true]);
        }

        $form = match (true) {
            isset($data['form_id']) => Form::find($data['form_id']),
            isset($data['form_key']) => Form::where('key', $data['form_key'])->first(),
            default => null,
        };

        if ($form) {
            abort_unless($form->is_active, 422, 'This form is no longer accepting submissions.');

            $missing = array_values(array_filter(
                $form->requiredFieldNames(),
                fn (string $name) => ! array_key_exists($name, $data['answers']) || $data['answers'][$name] === null || $data['answers'][$name] === '',
            ));

            if ($missing !== []) {
                throw ValidationException::withMessages(['answers' => ['Missing required answers: '.implode(', ', $missing).'.']]);
            }
        }

        $unit = PollingUnit::with('ward.lga')->findOrFail($data['polling_unit_id']);

        $submission = DB::transaction(function () use ($request, $data, $uuid, $form, $unit) {
            $submission = Submission::create([
                'uuid' => $uuid,
                'form_id' => $form?->id,
                'user_id' => $request->user()->id,
                'polling_unit_id' => $unit->id,
                'ward_id' => $unit->ward_id,
                'lga_id' => $unit->ward->lga_id,
                'state_id' => $unit->ward->lga->state_id,
                'location' => Submission::pointExpression((float) $data['latitude'], (float) $data['longitude']),
                'accuracy' => $data['accuracy'] ?? null,
                'collected_at' => CarbonImmutable::parse($data['collected_at'])->utc(),
                'answers' => $data['answers'],
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $this->storePhoto($submission, $photo);
            }

            return $submission;
        });

        return response()->json(['submission' => $this->present($this->reload($submission)), 'duplicate' => false], 201);
    }

    /** Attach one more photo; lets a device upload photos one at a time on a poor connection. */
    public function addPhoto(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($submission->user_id === $request->user()->id, 403, 'Only the agent who made a submission can add photos to it.');

        $request->validate(['photo' => ['required', ...$this->photoRules()]]);

        $max = (int) config('fielddata.max_photos');
        abort_if($submission->photos()->count() >= $max, 422, "A submission can have at most {$max} photos.");

        $photo = $this->storePhoto($submission, $request->file('photo'));

        return response()->json(['photo' => $this->presentPhoto($submission, $photo)], 201);
    }

    public function photo(Request $request, Submission $submission, SubmissionPhoto $photo): StreamedResponse
    {
        $this->authorizeView($request, $submission);
        abort_unless($photo->submission_id === $submission->id, 404);

        $disk = Storage::disk($photo->disk);
        abort_unless($disk->exists($photo->path), 404);

        return $disk->response($photo->path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** CSV export of the submissions the requester may see. Every export is audit-logged. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $data = $request->validate($this->filterRules());
        $limit = (int) config('fielddata.export_max_rows');

        $query = $this->filtered($request, $data)
            ->withCoordinates()
            ->with(['user:id,name', 'form:id,key', 'pollingUnit:id,code,name,ward_id', 'pollingUnit.ward:id,code,name,lga_id', 'pollingUnit.ward.lga:id,code,name,state_id', 'pollingUnit.ward.lga.state:id,code,name'])
            ->withCount('photos')
            ->orderBy('id')
            ->limit($limit);

        $audit->record($request, AuditLogger::SUBMISSIONS_EXPORTED, null, [
            'filters' => array_filter($data, fn ($value) => $value !== null),
            'rows' => min($limit, $this->filtered($request, $data)->count()),
        ]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'uuid', 'form', 'agent_id', 'agent_name', 'state', 'lga', 'ward', 'polling_unit_code', 'polling_unit_name', 'latitude', 'longitude', 'accuracy', 'collected_at', 'photos', 'answers'], ',', '"', '');

            foreach ($query->lazy(500) as $s) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $s->id,
                    $s->uuid,
                    $s->form?->key,
                    $s->user_id,
                    $s->user?->name,
                    $s->pollingUnit?->ward?->lga?->state?->name,
                    $s->pollingUnit?->ward?->lga?->name,
                    $s->pollingUnit?->ward?->name,
                    $s->pollingUnit?->code,
                    $s->pollingUnit?->name,
                    $s->latitude,
                    $s->longitude,
                    $s->accuracy,
                    $s->collected_at->toIso8601ZuluString(),
                    $s->photos_count,
                    json_encode($s->answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]), ',', '"', '');
            }

            fclose($out);
        }, 'submissions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stop spreadsheet applications from running cell contents as formulas. */
    private static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function filterRules(): array
    {
        return [
            'form_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'polling_unit_id' => ['nullable', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'lga_id' => ['nullable', 'integer'],
            'state_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function filtered(Request $request, array $data): Builder
    {
        $query = Submission::query()->visibleTo($request->user());

        foreach (['form_id', 'user_id', 'polling_unit_id', 'ward_id', 'lga_id', 'state_id'] as $column) {
            if (isset($data[$column])) {
                $query->where("submissions.{$column}", $data[$column]);
            }
        }

        return $query
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('collected_at', '>=', CarbonImmutable::parse($from)->utc()))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('collected_at', '<=', CarbonImmutable::parse($to)->utc()));
    }

    private function authorizeView(Request $request, Submission $submission): void
    {
        abort_unless(
            Submission::query()->visibleTo($request->user())->whereKey($submission->id)->exists(),
            403,
            'This submission is outside your area.',
        );
    }

    /** @return list<mixed> */
    private function photoRules(): array
    {
        return ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.(int) config('fielddata.photo_max_kb')];
    }

    private function storePhoto(Submission $submission, UploadedFile $file): SubmissionPhoto
    {
        $disk = (string) config('fielddata.photo_disk');

        return $submission->photos()->create([
            'disk' => $disk,
            'path' => $file->store("submissions/{$submission->id}", $disk),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    private function reload(Submission $submission): Submission
    {
        return Submission::query()
            ->withCoordinates()
            ->with(['user:id,name', 'form:id,key,name', 'pollingUnit:id,code,name', 'photos'])
            ->findOrFail($submission->id);
    }

    /** @return array<string, mixed> */
    private function present(Submission $s): array
    {
        return [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'form' => $s->form ? ['id' => $s->form->id, 'key' => $s->form->key, 'name' => $s->form->name] : null,
            'agent' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name] : null,
            'polling_unit' => $s->pollingUnit ? ['id' => $s->pollingUnit->id, 'code' => $s->pollingUnit->code, 'name' => $s->pollingUnit->name] : null,
            'ward_id' => $s->ward_id,
            'lga_id' => $s->lga_id,
            'state_id' => $s->state_id,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'accuracy' => $s->accuracy,
            'collected_at' => $s->collected_at->toIso8601ZuluString(),
            'answers' => (object) $s->answers,
            'photos' => $s->photos->map(fn (SubmissionPhoto $p) => $this->presentPhoto($s, $p))->all(),
            'created_at' => $s->created_at?->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentPhoto(Submission $submission, SubmissionPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'original_name' => $photo->original_name,
            'mime_type' => $photo->mime_type,
            'size' => $photo->size,
            'url' => route('submissions.photos.show', [$submission->id, $photo->id]),
        ];
    }
}
