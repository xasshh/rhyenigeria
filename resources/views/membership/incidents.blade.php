<x-layouts.app title="Report an incident" heading="Report an incident">
    <p class="mt-1 text-neutral-600">Seen something wrong at a polling unit, a rally or anywhere in your area? Report it here and it goes straight to the command centre, with your photos. If you or anyone else is in danger, get to safety first and call the police (112).</p>

    {{-- New report --}}
    <form method="POST" action="{{ route('membership.incidents.store') }}" enctype="multipart/form-data" class="panel mt-5 space-y-5 p-5"
          x-data="incidentForm()" @submit.prevent="send($event)">
        @csrf
        <div>
            <p class="label">What is happening?</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($categories as $category)
                    <label class="choice justify-start! text-left!"><input type="radio" name="category" value="{{ $category->value }}" class="sr-only" required @checked(old('category') === $category->value)> {{ $category->label() }}</label>
                @endforeach
            </div>
            @error('category')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div>
            <p class="label">How urgent is it?</p>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($urgencies as $urgency)
                    <label @class(['choice', 'has-[:checked]:border-red-600! has-[:checked]:bg-red-50! has-[:checked]:text-red-800!' => $urgency->value === 'critical'])><input type="radio" name="urgency" value="{{ $urgency->value }}" class="sr-only" required @checked(old('urgency', 'serious') === $urgency->value)> {{ $urgency->label() }}</label>
                @endforeach
            </div>
            @error('urgency')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="description">Describe what you see</label>
            <textarea id="description" name="description" rows="4" class="field" required minlength="10" maxlength="3000" placeholder="What is happening, who is involved, how many people, and what has happened so far.">{{ old('description') }}</textarea>
            @error('description')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="polling_unit">Where? (polling unit or place)</label>
            @if ($pollingUnits->isNotEmpty())
                <select id="polling_unit" name="polling_unit_id" class="field">
                    <option value="">Not at a polling unit in my ward</option>
                    @foreach ($pollingUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((int) old('polling_unit_id', $myUnitId) === $unit->id)>{{ $unit->code }} {{ $unit->name }}</option>
                    @endforeach
                </select>
                <input name="polling_unit_name" class="field mt-2" maxlength="120" value="{{ old('polling_unit_name') }}" placeholder="Or type the place">
            @else
                <input id="polling_unit" name="polling_unit_name" class="field" maxlength="120" value="{{ old('polling_unit_name', $myUnitName) }}" placeholder="e.g. PU 004 Gidan Waya Primary School">
            @endif
            <p class="hint">Your ward: {{ collect([$user->ward?->name, $user->lga?->name])->filter()->implode(', ') ?: 'not set' }}.</p>
        </div>

        <div>
            <p class="label">Photos (up to {{ \App\Models\Incident::MAX_PHOTOS }})</p>
            <label class="btn-secondary w-full cursor-pointer sm:w-auto">
                <x-icon name="camera" class="h-5 w-5" /> Take or choose photos
                <input type="file" name="photos[]" accept="image/*" multiple class="sr-only" x-ref="photos" @change="preview($event)">
            </label>
            <div class="mt-3 flex flex-wrap gap-2" x-show="thumbs.length" x-cloak>
                <template x-for="(src, i) in thumbs" :key="i"><img :src="src" alt="" class="h-20 w-20 rounded-xl object-cover ring-1 ring-line"></template>
            </div>
            <p class="error" x-show="photoError" x-text="photoError" x-cloak></p>
            @error('photos')<p class="error">{{ $message }}</p>@enderror
            @error('photos.*')<p class="error">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" x-model="attach" class="mt-0.5 h-5 w-5 rounded border-line text-brand">
            <span>Send my current location with this report, so the command centre can see exactly where it is. <span class="text-neutral-500" x-text="locationNote"></span></span>
        </label>
        <input type="hidden" name="latitude" x-ref="lat"><input type="hidden" name="longitude" x-ref="lng"><input type="hidden" name="accuracy" x-ref="acc">

        <button class="btn-primary w-full bg-red-700! hover:bg-red-800! sm:w-auto" :disabled="sending">
            <x-icon name="alert" class="h-5 w-5" /> <span x-text="shrinking ? 'Preparing photos…' : (sending ? 'Sending…' : 'Send to the command centre')">Send to the command centre</span>
        </button>
    </form>

    {{-- My reports --}}
    @if ($incidents->isNotEmpty())
        <h2 class="mt-8 font-display text-xl font-bold">My reports</h2>
        <div class="mt-3 space-y-4">
            @foreach ($incidents as $incident)
                <article class="panel p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $incident->category->label() }}</p>
                            <p class="text-sm text-neutral-500">{{ $incident->created_at->setTimezone(config('membership.timezone'))->format('j M, g:ia') }}{{ $incident->place() ? ' · '.$incident->place() : '' }}</p>
                        </div>
                        <span @class(['badge', 'bg-amber-100 text-amber-900' => $incident->status === 'new', 'bg-brand/10 text-brand-dark' => $incident->status === 'seen', 'bg-neutral-100 text-neutral-700' => ! $incident->isOpen()])>{{ \App\Models\Incident::STATUSES[$incident->status] }}</span>
                    </div>
                    <p class="mt-2 text-sm whitespace-pre-line text-neutral-800">{{ $incident->description }}</p>
                    @if ($incident->photos->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($incident->photos as $photo)
                                <a href="{{ route('incidents.photos.show', $photo) }}" target="_blank"><img src="{{ route('incidents.photos.show', $photo) }}" alt="Photo {{ $loop->iteration }}" class="h-16 w-16 rounded-lg object-cover ring-1 ring-line" loading="lazy"></a>
                            @endforeach
                        </div>
                    @endif
                    @foreach ($incident->updates as $update)
                        <div @class(['mt-3 rounded-xl p-3 text-sm', 'bg-brand/5 ring-1 ring-brand/20' => $update->from_command, 'bg-surface' => ! $update->from_command])>
                            <p class="text-xs font-semibold text-neutral-500">{{ $update->from_command ? 'Command centre' : 'Your update' }} · {{ $update->created_at->setTimezone(config('membership.timezone'))->format('j M, g:ia') }}</p>
                            <p class="mt-0.5 whitespace-pre-line">{{ $update->body }}</p>
                        </div>
                    @endforeach
                    @if ($incident->isOpen())
                        <form method="POST" action="{{ route('membership.incidents.updates.store', $incident) }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start" x-data="{ open: false }">
                            @csrf
                            <button type="button" class="btn-secondary btn-sm" x-show="!open" @click="open = true">Send an update</button>
                            <template x-if="open">
                                <div class="flex w-full flex-col gap-2">
                                    <textarea name="body" rows="2" class="field" required minlength="3" maxlength="2000" placeholder="What has changed?"></textarea>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="btn-secondary btn-sm cursor-pointer"><x-icon name="camera" class="h-4 w-4" /> Add photos <input type="file" name="photos[]" accept="image/*" multiple class="sr-only" @change="shrinkPhotos($event.target)"></label>
                                        <button class="btn-primary btn-sm">Send update</button>
                                    </div>
                                </div>
                            </template>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <script>
        /**
         * Phone photos are several MB; shrink each one to 1600px JPEG in the browser before it is sent,
         * so reports go through quickly on mobile data. Anything the browser cannot read is sent as it is.
         */
        window.shrinkPhotos = async (input) => {
            if (!window.DataTransfer || !window.createImageBitmap) return;
            const out = new DataTransfer();
            for (const file of [...input.files].slice(0, {{ \App\Models\Incident::MAX_PHOTOS }})) {
                try {
                    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(bitmap.width * scale);
                    canvas.height = Math.round(bitmap.height * scale);
                    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                    const blob = await new Promise((done) => canvas.toBlob(done, 'image/jpeg', 0.82));
                    out.items.add(blob && blob.size < file.size ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
                } catch {
                    out.items.add(file);
                }
            }
            input.files = out.files;
        };

        document.addEventListener('alpine:init', () => {
            Alpine.data('incidentForm', () => ({
                attach: true,
                sending: false,
                shrinking: false,
                thumbs: [],
                photoError: '',
                locationNote: '',
                async preview(event) {
                    const files = [...event.target.files];
                    this.photoError = files.length > {{ \App\Models\Incident::MAX_PHOTOS }} ? 'Choose up to {{ \App\Models\Incident::MAX_PHOTOS }} photos.' : '';
                    this.thumbs.forEach((u) => URL.revokeObjectURL(u));
                    this.thumbs = files.slice(0, {{ \App\Models\Incident::MAX_PHOTOS }}).map((f) => URL.createObjectURL(f));
                    this.shrinking = true;
                    await window.shrinkPhotos(event.target);
                    this.shrinking = false;
                },
                // Ask for the location only when sending, then submit whatever the answer.
                send(event) {
                    if (this.sending || this.shrinking || this.photoError) return;
                    const form = event.target;
                    this.sending = true;
                    if (!this.attach || !navigator.geolocation) { form.submit(); return; }
                    this.locationNote = 'Finding your location…';
                    navigator.geolocation.getCurrentPosition((pos) => {
                        this.$refs.lat.value = pos.coords.latitude;
                        this.$refs.lng.value = pos.coords.longitude;
                        this.$refs.acc.value = Math.round(pos.coords.accuracy);
                        form.submit();
                    }, () => form.submit(), { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
                },
            }));
        });
    </script>
</x-layouts.app>
