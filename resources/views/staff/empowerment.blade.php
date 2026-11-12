<x-layouts.app title="Empowerment" heading="Empowerment beneficiaries">
    <form method="GET" class="mt-4 flex max-w-md gap-2">
        <input name="q" value="{{ request('q') }}" class="field py-2" placeholder="Search programme">
        <button class="btn-secondary btn-sm">Search</button>
    </form>
    <p class="mt-3 text-sm text-neutral-600">{{ number_format($reports->total()) }} {{ str('report')->plural($reports->total()) }}</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($reports as $r)
            <article class="panel overflow-hidden">
                @if ($r->photo_path)
                    <a href="{{ route('empowerment.photo', $r) }}" target="_blank"><img src="{{ route('empowerment.photo', $r) }}" alt="Photo from {{ $r->user->name }}" class="aspect-[4/3] w-full bg-surface object-cover" loading="lazy"></a>
                @else
                    <div class="flex aspect-[4/3] items-center justify-center bg-surface text-sm text-neutral-400">No photo</div>
                @endif
                <div class="p-4 text-sm">
                    <p class="font-display font-bold">{{ $r->programme }}</p>
                    <p class="text-neutral-500">{{ $r->received_on?->format('M Y') ?? 'Date not given' }}</p>
                    <p class="mt-1 line-clamp-3">{{ $r->what_received }}</p>
                    <a href="{{ staff_route('members.show', $r->user) }}" class="mt-2 block font-medium text-brand-dark hover:underline">{{ $r->user->name }}</a>
                    <p class="text-xs text-neutral-500">{{ collect([$r->user->ward?->name, $r->user->lga?->name, $r->user->state?->name])->filter()->implode(', ') }}</p>
                </div>
            </article>
        @empty
            <p class="panel p-8 text-center text-neutral-500 sm:col-span-2 lg:col-span-3">No empowerment reports yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-layouts.app>
