<x-layouts.app title="Homepage slides">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Homepage slides</h1>
            <p class="mt-1 text-sm text-neutral-600">The header slider on <a href="{{ route('home') }}" class="font-medium text-brand underline" target="_blank">the homepage</a>. Slides show in order of their number; only slides that are on are shown.</p>
        </div>
        <a href="{{ route('admin.slides.create') }}" class="btn-primary btn-sm"><x-icon name="image" class="h-4 w-4" /> Add a slide</a>
    </div>

    <ul class="mt-5 space-y-3">
        @forelse ($slides as $slide)
            <li class="panel flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                <div class="flex min-w-0 flex-1 items-center gap-4">
                    <span class="w-8 shrink-0 text-center font-display text-lg font-bold text-neutral-400">{{ $slide->position }}</span>
                    @if ($slide->imageUrl())
                        <img src="{{ $slide->imageUrl() }}" alt="" class="h-20 w-16 shrink-0 rounded-lg object-cover ring-1 ring-line" loading="lazy">
                    @else
                        <span class="flex h-20 w-16 shrink-0 items-center justify-center rounded-lg bg-surface text-neutral-400 ring-1 ring-line"><x-icon name="image" /></span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $slide->headline }}</p>
                        <p class="truncate text-sm text-neutral-600">{{ $slide->eyebrow }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $slide->button_label ? $slide->button_label.' → '.$slide->button_url : 'No button' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <form method="POST" action="{{ route('admin.slides.toggle', $slide) }}">
                        @csrf @method('PATCH')
                        <button @class(['badge min-h-9 px-3', 'bg-brand text-white' => $slide->is_active, 'bg-surface text-neutral-600 ring-1 ring-line' => ! $slide->is_active]) aria-label="{{ $slide->is_active ? 'On the homepage; click to hide' : 'Hidden; click to show' }}">{{ $slide->is_active ? 'On' : 'Off' }}</button>
                    </form>
                    <a href="{{ route('admin.slides.edit', $slide) }}" class="btn-secondary btn-sm">Edit</a>
                </div>
            </li>
        @empty
            <li class="panel p-6 text-center text-neutral-600">No slides yet. The homepage shows a plain header until you add one.</li>
        @endforelse
    </ul>
</x-layouts.app>
