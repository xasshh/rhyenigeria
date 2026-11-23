<x-layouts.app title="X posts">
    <div>
        <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">X posts</h1>
        <p class="mt-1 text-sm text-neutral-600">
            Posts from X (Twitter) shown on <a href="{{ route('home') }}#x-feed" class="font-medium text-brand underline" target="_blank">the homepage</a>, newest first. The latest {{ \App\Models\XPost::HOMEPAGE_COUNT }} are shown.
            @if ($handle) Follow <a href="https://x.com/{{ $handle }}" target="_blank" rel="noopener" class="font-medium text-brand underline">{{ '@'.$handle }}</a> and add each new post here. @endif
        </p>
    </div>

    <form method="POST" action="{{ route('admin.x-posts.store') }}" class="panel mt-5 p-4 sm:p-5">
        @csrf
        <label for="link" class="text-sm font-semibold">Link to the post</label>
        <p class="mt-0.5 text-xs text-neutral-500">On X, open the post, tap <strong>Share → Copy link</strong>, and paste it here. You can paste several links, one per line.</p>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
            <textarea id="link" name="link" rows="1" required class="field min-h-11 flex-1" placeholder="https://x.com/{{ $handle ?? 'account' }}/status/1234567890123456789" @error('link') aria-invalid="true" aria-describedby="link-error" @enderror>{{ old('link') }}</textarea>
            <button class="btn-primary shrink-0">Add to homepage</button>
        </div>
        @error('link')<p id="link-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </form>

    <ul class="mt-5 space-y-3">
        @forelse ($posts as $post)
            <li class="panel flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <a href="{{ $post->url() }}" target="_blank" rel="noopener" class="block truncate font-medium text-brand hover:underline">{{ $post->url() }}</a>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        {{ '@'.$post->handle }}
                        @if ($post->posted_at) · posted {{ $post->posted_at->setTimezone(config('membership.timezone'))->format('j M Y, g:ia') }} @endif
                        @if ($post->addedBy) · added by {{ $post->addedBy->name }} @endif
                        @if ($loop->index < \App\Models\XPost::HOMEPAGE_COUNT && $posts->currentPage() === 1) · <span class="font-semibold text-brand">on the homepage</span> @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.x-posts.destroy', $post) }}">
                    @csrf @method('DELETE')
                    <button class="btn-secondary btn-sm">Remove</button>
                </form>
            </li>
        @empty
            <li class="panel p-6 text-center text-neutral-600">No posts yet. The homepage hides the X section until you add one.</li>
        @endforelse
    </ul>
    <div class="mt-4">{{ $posts->links() }}</div>
</x-layouts.app>
