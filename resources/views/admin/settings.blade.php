<x-layouts.app title="Settings" heading="Settings">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 space-y-4">
        @csrf @method('PUT')
        <section class="panel space-y-4 p-5">
            <h2 class="font-display text-lg font-bold">Election countdown</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="election_date">Date and time (Nigeria time)</label>
                    <input id="election_date" name="election_date" type="datetime-local" class="field" value="{{ old('election_date', $electionDate?->format('Y-m-d\TH:i')) }}">
                    @error('election_date')<p class="error">{{ $message }}</p>@enderror
                    <p class="hint">Shown on the homepage and member dashboards. Leave empty to use Saturday 16 January 2027.</p>
                </div>
                <div>
                    <label class="label" for="election_label">Label</label>
                    <input id="election_label" name="election_label" class="field" maxlength="80" value="{{ old('election_label', $electionLabel) }}" placeholder="{{ \App\Models\Setting::DEFAULT_ELECTION_LABEL }}">
                </div>
            </div>
        </section>

        <section class="panel p-5">
            <h2 class="font-display text-lg font-bold">State WhatsApp groups</h2>
            <p class="mt-1 text-sm text-neutral-600">New members see a “Join your state WhatsApp group” button when their state has a link.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($states as $s)
                    <div>
                        <label class="label" for="wa-{{ $s->id }}">{{ $s->name }}</label>
                        <input id="wa-{{ $s->id }}" name="whatsapp[{{ $s->id }}]" type="url" class="field py-2 text-sm" placeholder="https://chat.whatsapp.com/…" value="{{ old('whatsapp.'.$s->id, $s->whatsapp_group_url) }}">
                        @error('whatsapp.'.$s->id)<p class="error">{{ $message }}</p>@enderror
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">No states imported yet.</p>
                @endforelse
            </div>
        </section>
        <button class="btn-primary">Save settings</button>
    </form>
</x-layouts.app>
