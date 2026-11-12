<x-layouts.app title="Empowerment" heading="Empowerment">
    <p class="mt-1 text-neutral-600">Tell us about empowerment programmes that reached you. It helps us show what is working in your area.</p>

    @foreach ($reports as $report)
        <article class="panel mt-4 flex gap-4 p-4">
            @if ($report->photo_path)
                <img src="{{ route('empowerment.photo', $report) }}" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover" loading="lazy">
            @endif
            <div class="min-w-0">
                <h3 class="font-display font-bold">{{ $report->programme }}</h3>
                <p class="text-sm text-neutral-500">{{ $report->received_on?->format('M Y') ?? 'Date not given' }}</p>
                <p class="mt-1 text-sm text-neutral-700">{{ $report->what_received }}</p>
            </div>
        </article>
    @endforeach

    <section class="panel mt-5 p-5" x-data="{ answer: @js(old('benefited', $reports->isNotEmpty() ? '1' : null)) }">
        <h2 class="font-display text-lg font-bold">{{ $reports->isEmpty() ? 'Have you benefited from any empowerment programme?' : 'Add another programme' }}</h2>

        @if ($reports->isEmpty())
            <div class="mt-4 grid max-w-sm grid-cols-2 gap-2">
                <label class="choice"><input type="radio" class="sr-only" value="1" x-model="answer"> Yes</label>
                <form method="POST" action="{{ route('membership.empowerment.store') }}" class="contents">
                    @csrf <input type="hidden" name="benefited" value="0">
                    <button class="choice {{ $profile->empowerment_benefited === false ? 'border-brand bg-brand/10 text-brand-dark' : '' }}">No</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('membership.empowerment.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4" x-show="answer === '1'" @if ($reports->isEmpty()) x-cloak @endif>
            @csrf <input type="hidden" name="benefited" value="1">
            <div>
                <label class="label" for="programme">Which programme?</label>
                <input id="programme" name="programme" class="field" value="{{ old('programme') }}" placeholder="e.g. Youth grant, skills training" required>
                @error('programme')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="max-w-xs">
                <label class="label" for="received_on">When did you receive it?</label>
                <input id="received_on" name="received_on" type="date" class="field" value="{{ old('received_on') }}" max="{{ now()->toDateString() }}">
                @error('received_on')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="what_received">What did you receive?</label>
                <textarea id="what_received" name="what_received" rows="3" class="field" required placeholder="e.g. A sewing machine and three months of training">{{ old('what_received') }}</textarea>
                @error('what_received')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="photo">A photo of you with it <span class="font-normal text-neutral-500">(optional)</span></label>
                <input id="photo" name="photo" type="file" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand/10 file:px-4 file:py-2.5 file:font-semibold file:text-brand-dark">
                <p class="hint">Kept private: only you, your state coordinator and the national team can see it.</p>
                @error('photo')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary">Save</button>
        </form>
    </section>
</x-layouts.app>
