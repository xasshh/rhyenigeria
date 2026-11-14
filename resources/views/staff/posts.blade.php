@php $isAdmin = auth()->user()->isSuperAdmin(); $tz = config('membership.timezone'); @endphp
<x-layouts.app title="News & events" heading="News & events">
    <p class="mt-1 text-sm text-neutral-600">{{ $isAdmin ? 'Post nationally, or to one state.' : 'Posts go to members in '.(auth()->user()->state?->name ?? 'your state').'.' }}</p>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ staff_route('news.store') }}" enctype="multipart/form-data" class="panel space-y-4 p-5">
            @csrf
            <h2 class="font-display text-lg font-bold">Post an update</h2>
            <div><label class="label" for="n-title">Title</label><input id="n-title" name="title" class="field" required maxlength="255" value="{{ old('title') }}"></div>
            <div><label class="label" for="n-body">Message</label><textarea id="n-body" name="body" rows="4" class="field" required>{{ old('body') }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="n-source">Source label</label><input id="n-source" name="source_label" class="field" maxlength="64" placeholder="e.g. INEC" value="{{ old('source_label') }}"></div>
                @if ($isAdmin)
                    <div><label class="label" for="n-state">Who sees it</label>
                        <select id="n-state" name="state_id" class="field"><option value="">Everyone (national)</option>@foreach ($states as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                @elseif ($wards->isNotEmpty())
                    <div><label class="label" for="n-ward">Only one ward? <span class="font-normal text-neutral-500">(optional)</span></label>
                        <select id="n-ward" name="ward_id" class="field"><option value="">Whole state</option>@foreach ($wards->groupBy('lga_name') as $lga => $ws)<optgroup label="{{ $lga }}">@foreach ($ws as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</optgroup>@endforeach</select></div>
                @endif
            </div>
            <div>
                <label class="label" for="n-photos">Photos <span class="font-normal text-neutral-500">(optional, up to 10)</span></label>
                <input id="n-photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                @error('photos')<p class="error">{{ $message }}</p>@enderror
                @error('photos.*')<p class="error">{{ $message }}</p>@enderror
            </div>
            @if ($isAdmin)
                <label class="flex items-start gap-2 rounded-xl border border-brand/25 bg-brand/5 p-3 text-sm"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" class="mt-0.5 h-4 w-4 accent-brand" @checked(old('is_public'))> <span><strong>Show on the public website</strong> as a news story with its photo gallery (homepage and /news). Leave unticked for members only.</span></label>
            @endif
            <button class="btn-primary">Post update</button>
        </form>

        <form method="POST" action="{{ staff_route('events.store') }}" class="panel space-y-4 p-5">
            @csrf
            <h2 class="font-display text-lg font-bold">Add an event</h2>
            <div><label class="label" for="e-title">Title</label><input id="e-title" name="title" class="field" required maxlength="255" placeholder="e.g. Youth rally"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="e-start">Starts (Nigeria time)</label><input id="e-start" name="starts_at" type="datetime-local" class="field" required></div>
                <div><label class="label" for="e-venue">Venue</label><input id="e-venue" name="venue" class="field" maxlength="255"></div>
            </div>
            <div><label class="label" for="e-desc">Details</label><textarea id="e-desc" name="description" rows="2" class="field"></textarea></div>
            @if ($isAdmin)
                <div><label class="label" for="e-state">Where</label>
                    <select id="e-state" name="state_id" class="field"><option value="">National</option>@foreach ($states as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
            @endif
            <button class="btn-primary">Add event</button>
        </form>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section>
            <h2 class="font-display text-lg font-bold">Updates</h2>
            <ul class="panel mt-3 divide-y divide-line">
                @forelse ($news as $post)
                    <li class="p-4">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-semibold">{{ $post->title }} @if ($post->is_public && $post->slug)<a href="{{ route('news.show', $post->slug) }}" target="_blank" class="badge bg-brand text-white">On website</a>@endif @if ($post->photos->isNotEmpty())<span class="badge bg-surface text-neutral-600">{{ $post->photos->count() }} photos</span>@endif</p>
                                <p class="text-xs text-neutral-500">{{ $post->ward?->name ?? $post->state?->name ?? 'National' }} · {{ $post->published_at->setTimezone($tz)->format('j M Y') }}{{ $post->author ? ' · '.$post->author->name : '' }}</p>
                            </div>
                            @if ($isAdmin || $post->state_id === auth()->user()->state_id)
                                <form method="POST" action="{{ staff_route('news.destroy', $post) }}" x-data x-on:submit="if (!confirm('Remove this update?')) $event.preventDefault()">@csrf @method('DELETE')<button class="text-sm text-red-700">Remove</button></form>
                            @endif
                        </div>
                        @if ($isAdmin || $post->state_id === auth()->user()->state_id)
                            <details class="group mt-2">
                                <summary class="cursor-pointer text-sm font-medium text-brand">Edit</summary>
                                <form method="POST" action="{{ staff_route('news.update', $post) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                                    @csrf @method('PUT')
                                    <input name="title" class="field py-2 text-sm" maxlength="255" required value="{{ $post->title }}" aria-label="Title">
                                    <textarea name="body" class="field py-2 text-sm" rows="4" maxlength="10000" required aria-label="Text">{{ $post->body }}</textarea>
                                    <input name="source_label" class="field py-2 text-sm" maxlength="64" value="{{ $post->source_label }}" placeholder="Source (optional)" aria-label="Source">
                                    @if ($isAdmin)
                                        <select name="state_id" class="field py-2 text-sm" aria-label="State"><option value="">Everyone (national)</option>@foreach ($states as $s)<option value="{{ $s->id }}" @selected($post->state_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                                        @if ($post->ward_id)<input type="hidden" name="ward_id" value="{{ $post->ward_id }}"><p class="hint">Stays limited to {{ $post->ward?->name }} ward.</p>@endif
                                    @else
                                        <select name="ward_id" class="field py-2 text-sm" aria-label="Ward"><option value="">Whole state</option>@foreach ($wards->groupBy('lga_name') as $lga => $ws)<optgroup label="{{ $lga }}">@foreach ($ws as $w)<option value="{{ $w->id }}" @selected($post->ward_id === $w->id)>{{ $w->name }}</option>@endforeach</optgroup>@endforeach</select>
                                    @endif
                                    <input name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" aria-label="Add photos" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                                    @if ($isAdmin)
                                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" class="h-4 w-4 accent-brand" @checked($post->is_public)> Show on the public website</label>
                                    @endif
                                    <button class="btn-primary btn-sm">Save changes</button>
                                </form>
                                @if ($post->photos->isNotEmpty())
                                    <ul class="mt-3 grid grid-cols-4 gap-2">
                                        @foreach ($post->photos as $photo)
                                            <li class="relative">
                                                <img src="{{ $photo->url() }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                                                <form method="POST" action="{{ staff_route('news.photos.destroy', [$post, $photo]) }}" class="absolute top-1 right-1">@csrf @method('DELETE')<button class="rounded-full bg-white/90 px-2 text-xs font-semibold text-red-700 shadow" aria-label="Remove photo">✕</button></form>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </details>
                        @endif
                    </li>
                @empty
                    <li class="p-5 text-sm text-neutral-500">No updates yet.</li>
                @endforelse
            </ul>
            <div class="mt-3">{{ $news->links() }}</div>
        </section>
        <section>
            <h2 class="font-display text-lg font-bold">Events</h2>
            <ul class="panel mt-3 divide-y divide-line">
                @forelse ($events as $event)
                    <li class="p-4">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-semibold">{{ $event->title }} @if ($event->starts_at->isPast())<span class="badge bg-neutral-100 text-neutral-500">Past</span>@endif</p>
                                <p class="text-xs text-neutral-500">{{ $event->state?->name ?? 'National' }} · {{ $event->starts_at->setTimezone($tz)->format('D j M Y, g:ia') }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                            </div>
                            @if ($isAdmin || $event->state_id === auth()->user()->state_id)
                                <form method="POST" action="{{ staff_route('events.destroy', $event) }}" x-data x-on:submit="if (!confirm('Remove this event?')) $event.preventDefault()">@csrf @method('DELETE')<button class="text-sm text-red-700">Remove</button></form>
                            @endif
                        </div>
                        @if ($isAdmin || $event->state_id === auth()->user()->state_id)
                            <details class="group mt-2">
                                <summary class="cursor-pointer text-sm font-medium text-brand">Edit</summary>
                                <form method="POST" action="{{ staff_route('events.update', $event) }}" class="mt-3 space-y-3">
                                    @csrf @method('PUT')
                                    <input name="title" class="field py-2 text-sm" maxlength="255" required value="{{ $event->title }}" aria-label="Title">
                                    <input name="starts_at" type="datetime-local" class="field py-2 text-sm" required value="{{ $event->starts_at->setTimezone($tz)->format('Y-m-d\TH:i') }}" aria-label="Date and time">
                                    <input name="venue" class="field py-2 text-sm" maxlength="255" value="{{ $event->venue }}" placeholder="Venue" aria-label="Venue">
                                    <textarea name="description" class="field py-2 text-sm" rows="3" maxlength="5000" aria-label="Description">{{ $event->description }}</textarea>
                                    @if ($isAdmin)
                                        <select name="state_id" class="field py-2 text-sm" aria-label="State"><option value="">National</option>@foreach ($states as $s)<option value="{{ $s->id }}" @selected($event->state_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                                    @endif
                                    <button class="btn-primary btn-sm">Save changes</button>
                                </form>
                            </details>
                        @endif
                    </li>
                @empty
                    <li class="p-5 text-sm text-neutral-500">No events yet.</li>
                @endforelse
            </ul>
            <div class="mt-3">{{ $events->links() }}</div>
        </section>
    </div>
</x-layouts.app>
