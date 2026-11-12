@if (session('status'))
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-brand/30 bg-brand/10 px-4 py-3 text-sm text-brand-dark']) }} role="status">
        <x-icon name="check" class="mt-0.5 h-4 w-4" />
        <span>{{ session('status') }}</span>
    </div>
@endif
@if ($errors->any() && ! ($quiet ?? false))
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800']) }} role="alert">
        {{ $errors->count() === 1 ? $errors->first() : 'Please check the highlighted fields.' }}
    </div>
@endif
