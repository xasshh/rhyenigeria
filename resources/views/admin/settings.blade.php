<x-layouts.app title="Settings" heading="Settings">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
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
            <h2 class="font-display text-lg font-bold">State WhatsApp groups and coordinators</h2>
            <p class="mt-1 text-sm text-neutral-600">The public <a href="{{ route('whatsapp') }}" class="font-semibold text-brand hover:underline" target="_blank">WhatsApp groups</a> page shows each state's coordinator name and photo. The group link itself is only shown to signed-in members of that state, on their dashboard.</p>
            <div class="mt-4 divide-y divide-line">
                @forelse ($states as $s)
                    <div class="grid gap-3 py-4 sm:grid-cols-[72px_minmax(0,1fr)] sm:gap-4">
                        <div class="flex items-start gap-3 sm:block">
                            @if ($s->coordinatorPhotoUrl())
                                <img src="{{ $s->coordinatorPhotoUrl() }}" alt="" class="h-16 w-16 rounded-xl object-cover object-top ring-1 ring-line">
                            @else
                                <span class="flex h-16 w-16 items-center justify-center rounded-xl bg-surface text-neutral-400"><x-icon name="users" /></span>
                            @endif
                        </div>
                        <div>
                            <p class="font-semibold">{{ $s->name }}</p>
                            <div class="mt-2 grid gap-2 lg:grid-cols-3">
                                <div>
                                    <label class="text-xs text-neutral-500" for="cn-{{ $s->id }}">Coordinator name</label>
                                    <input id="cn-{{ $s->id }}" name="coordinator_name[{{ $s->id }}]" class="field py-2 text-sm" maxlength="120" value="{{ old('coordinator_name.'.$s->id, $s->coordinator_name) }}">
                                </div>
                                <div>
                                    <label class="text-xs text-neutral-500" for="cp-{{ $s->id }}">{{ $s->coordinator_photo_path ? 'Replace photo' : 'Photo' }}</label>
                                    <input id="cp-{{ $s->id }}" name="coordinator_photo[{{ $s->id }}]" type="file" accept="image/jpeg,image/png,image/webp" class="field py-1.5 text-sm">
                                    @error('coordinator_photo.'.$s->id)<p class="error">{{ $message }}</p>@enderror
                                    @if ($s->coordinator_photo_path)
                                        <label class="mt-1 flex items-center gap-1.5 text-xs text-neutral-600"><input type="checkbox" name="remove_photo[{{ $s->id }}]" value="1" class="accent-brand"> Remove photo</label>
                                    @endif
                                </div>
                                <div>
                                    <label class="text-xs text-neutral-500" for="wa-{{ $s->id }}">WhatsApp group link</label>
                                    <input id="wa-{{ $s->id }}" name="whatsapp[{{ $s->id }}]" type="url" class="field py-2 text-sm" placeholder="https://chat.whatsapp.com/…" value="{{ old('whatsapp.'.$s->id, $s->whatsapp_group_url) }}">
                                    @error('whatsapp.'.$s->id)<p class="error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">No states imported yet.</p>
                @endforelse
            </div>
        </section>
        <button class="btn-primary">Save settings</button>
    </form>
</x-layouts.app>
