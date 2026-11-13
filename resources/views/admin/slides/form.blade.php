<x-layouts.app :title="$slide->exists ? 'Edit slide' : 'Add a slide'">
    <a href="{{ route('admin.slides.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand"><x-icon name="arrow-left" class="h-4 w-4" /> Homepage slides</a>
    <h1 class="mt-1 font-display text-2xl font-bold tracking-tight sm:text-[28px]">{{ $slide->exists ? 'Edit slide' : 'Add a slide' }}</h1>

    <form method="POST" enctype="multipart/form-data" action="{{ $slide->exists ? route('admin.slides.update', $slide) : route('admin.slides.store') }}" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
        @csrf
        @if ($slide->exists) @method('PUT') @endif

        <section class="panel space-y-4 p-5">
            <div>
                <label class="label" for="eyebrow">Small line above the headline <span class="font-normal text-neutral-500">(optional)</span></label>
                <input id="eyebrow" name="eyebrow" class="field" maxlength="80" value="{{ old('eyebrow', $slide->eyebrow) }}" placeholder="e.g. Plateau State · 13 November 2026">
                @error('eyebrow')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="headline">Headline</label>
                <input id="headline" name="headline" class="field" maxlength="160" required value="{{ old('headline', $slide->headline) }}">
                @error('headline')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="body">Short text <span class="font-normal text-neutral-500">(optional, up to 400 characters)</span></label>
                <textarea id="body" name="body" class="field" rows="3" maxlength="400">{{ old('body', $slide->body) }}</textarea>
                @error('body')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="button_label">Button label</label>
                    <input id="button_label" name="button_label" class="field" maxlength="40" value="{{ old('button_label', $slide->button_label) }}" placeholder="Join the Movement">
                    @error('button_label')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="button_url">Button link</label>
                    <input id="button_url" name="button_url" class="field" maxlength="255" value="{{ old('button_url', $slide->button_url) }}" placeholder="/join">
                    <p class="hint">A page on this site such as <code>/join</code>, or a full <code>https://</code> link. Leave both empty for no button.</p>
                    @error('button_url')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="space-y-4">
            <section class="panel space-y-4 p-5">
                <h2 class="font-display font-bold">Image</h2>
                @if ($slide->imageUrl())
                    <img src="{{ $slide->imageUrl() }}" alt="" class="w-full rounded-xl ring-1 ring-line">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1" class="h-4 w-4 accent-brand"> Remove this image</label>
                @endif
                <div>
                    <label class="label" for="image">{{ $slide->imageUrl() ? 'Replace with' : 'Upload' }}</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                    <p class="hint">JPEG, PNG or WebP, at least 600 px wide, up to 10 MB. A portrait (4:5) or square image fits best. A web-sized copy is made automatically; the original is kept.</p>
                    @error('image')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="image_alt">Describe the image <span class="font-normal text-neutral-500">(for screen readers)</span></label>
                    <input id="image_alt" name="image_alt" class="field" maxlength="255" value="{{ old('image_alt', $slide->image_alt) }}">
                </div>
            </section>
            <section class="panel space-y-4 p-5">
                <div>
                    <label class="label" for="layout">Layout</label>
                    <select id="layout" name="layout" class="field">
                        @foreach (\App\Models\HeroSlide::LAYOUTS as $value => $label)
                            <option value="{{ $value }}" @selected(old('layout', $slide->layout ?? 'image') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="hint">The original hero works best with a square portrait that already has its round crop.</p>
                    @error('layout')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="position">Order</label>
                    <input id="position" name="position" type="number" min="0" max="999" class="field" required value="{{ old('position', $slide->position) }}">
                    <p class="hint">Lower numbers come first.</p>
                    @error('position')<p class="error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-brand" @checked(old('is_active', $slide->is_active))> Show on the homepage</label>
            </section>
            <button class="btn-primary w-full">{{ $slide->exists ? 'Save slide' : 'Add slide' }}</button>
        </div>
    </form>

    @if ($slide->exists)
        <form method="POST" action="{{ route('admin.slides.destroy', $slide) }}" class="mt-6" x-data x-on:submit="if (!window.confirm('Delete this slide?')) $event.preventDefault()">
            @csrf @method('DELETE')
            <button class="text-sm font-medium text-red-700 hover:underline">Delete this slide</button>
        </form>
    @endif
</x-layouts.app>
