<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\NewsPhoto;
use App\Models\NewsPost;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use App\Support\WebImage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** News and events. Coordinators post to their own state; super admins nationally or to any state. */
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $scope = fn ($q) => $viewer->isSuperAdmin() ? $q : $q->where('state_id', $viewer->state_id);

        return view('staff.posts', [
            'events' => $scope(Event::query())->with('state:id,name', 'author:id,name')->latest('starts_at')->paginate(15, ['*'], 'events_page'),
            'news' => $scope(NewsPost::query())->with('state:id,name', 'ward:id,name', 'author:id,name', 'photos')->latest('published_at')->paginate(15, ['*'], 'news_page'),
            'states' => $viewer->isSuperAdmin() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'wards' => $viewer->state_id
                ? Ward::query()->join('lgas', 'lgas.id', '=', 'wards.lga_id')->where('lgas.state_id', $viewer->state_id)
                    ->orderBy('lgas.name')->orderBy('wards.name')->get(['wards.id', 'wards.name', 'lgas.name as lga_name'])
                : collect(),
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
        ]);

        $event = new Event([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'venue' => $data['venue'] ?? null,
            'starts_at' => CarbonImmutable::parse($data['starts_at'], config('membership.timezone'))->utc(),
        ]);
        $event->state_id = $this->targetState($request->user(), $data['state_id'] ?? null);
        $event->created_by = $request->user()->id;
        $event->save();

        return back()->with('status', 'Event posted.');
    }

    public function storeNews(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'source_label' => ['nullable', 'string', 'max:64'],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'ward_id' => ['nullable', 'integer'],
        ] + self::photoRules());

        $post = new NewsPost([
            'title' => $data['title'],
            'body' => $data['body'],
            'source_label' => $data['source_label'] ?? null,
            'published_at' => now(),
        ]);
        $post->state_id = $this->targetState($viewer, $data['state_id'] ?? null);

        if (! empty($data['ward_id'])) {
            $ward = Ward::with('lga')->findOrFail($data['ward_id']);
            abort_unless($post->state_id !== null && $ward->lga->state_id === $post->state_id, 422, 'That ward is not in the chosen state.');
            $post->ward_id = $ward->id;
        }

        $post->created_by = $viewer->id;
        $this->applyPublic($request, $post);
        $post->save();
        $this->storePhotos($request, $post);

        return back()->with('status', $post->is_public ? 'Update posted, and published on the website.' : 'Update posted.');
    }

    public function updateEvent(Request $request, Event $event): RedirectResponse
    {
        $viewer = $request->user();
        $this->authorizePost($viewer, $event->state_id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
        ]);

        $event->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'venue' => $data['venue'] ?? null,
            'starts_at' => CarbonImmutable::parse($data['starts_at'], config('membership.timezone'))->utc(),
        ]);
        // Coordinators cannot move a post out of their state; super admins can retarget it.
        $event->state_id = $this->targetState($viewer, $data['state_id'] ?? null);
        $event->save();

        return redirect()->to(staff_route('posts.index'))->with('status', 'Event updated.');
    }

    public function updateNews(Request $request, NewsPost $news): RedirectResponse
    {
        $viewer = $request->user();
        $this->authorizePost($viewer, $news->state_id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'source_label' => ['nullable', 'string', 'max:64'],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'ward_id' => ['nullable', 'integer'],
        ] + self::photoRules());

        $news->fill(['title' => $data['title'], 'body' => $data['body'], 'source_label' => $data['source_label'] ?? null]);
        $news->state_id = $this->targetState($viewer, $data['state_id'] ?? null);
        $news->ward_id = null;
        if (! empty($data['ward_id'])) {
            $ward = Ward::with('lga')->findOrFail($data['ward_id']);
            abort_unless($news->state_id !== null && $ward->lga->state_id === $news->state_id, 422, 'That ward is not in the chosen state.');
            $news->ward_id = $ward->id;
        }
        $this->applyPublic($request, $news);
        $news->save();
        $this->storePhotos($request, $news);

        return redirect()->to(staff_route('posts.index'))->with('status', 'Update saved.');
    }

    public function destroyEvent(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePost($request->user(), $event->state_id);
        $event->delete();

        return back()->with('status', 'Event removed.');
    }

    public function destroyNews(Request $request, NewsPost $news): RedirectResponse
    {
        $this->authorizePost($request->user(), $news->state_id);
        $news->photos->each->deleteFiles();
        $news->delete();

        return back()->with('status', 'Update removed.');
    }

    public function destroyPhoto(Request $request, NewsPost $news, NewsPhoto $photo): RedirectResponse
    {
        $this->authorizePost($request->user(), $news->state_id);
        abort_unless($photo->news_post_id === $news->id, 404);
        $photo->deleteFiles();
        $photo->delete();

        return back()->with('status', 'Photo removed.');
    }

    /** @return array<string, mixed> */
    private static function photoRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }

    /** Only super admins put a post on the public website; it then needs a readable address. */
    private function applyPublic(Request $request, NewsPost $post): void
    {
        if (! $request->user()->isSuperAdmin() || ! $request->has('is_public')) {
            return;
        }
        $post->is_public = $request->boolean('is_public');
        if ($post->is_public && ! $post->slug) {
            $post->slug = NewsPost::uniqueSlug($post->title);
        }
    }

    private function storePhotos(Request $request, NewsPost $post): void
    {
        $next = (int) $post->photos()->max('position');
        foreach ($request->file('photos', []) as $file) {
            [$original, $web] = WebImage::storeWithWebp($file, 'news/'.$post->id, 1600);
            $post->photos()->create(['path' => $web, 'original_path' => $original, 'position' => ++$next]);
        }
    }

    private function targetState(User $viewer, ?int $requested): ?int
    {
        if ($viewer->isSuperAdmin()) {
            return $requested;
        }

        abort_if($viewer->state_id === null, 403, 'You have no state assigned.');

        return $viewer->state_id;
    }

    private function authorizePost(User $viewer, ?int $stateId): void
    {
        abort_unless($viewer->isSuperAdmin() || ($stateId !== null && $stateId === $viewer->state_id), 403);
    }
}
