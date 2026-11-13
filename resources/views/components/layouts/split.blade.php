@props(['title' => null, 'noindex' => false, 'stats' => false, 'alpine' => null])
{{--
    Sign-in and join screens (RHYE sign-in & join PDF): a dark forest panel on the
    left with the headline and card, the form on the right. On phones the panel
    shrinks to a short band above the form.
--}}
<x-layouts.base :title="$title" :noindex="$noindex">
    <div class="min-h-dvh bg-white lg:grid lg:grid-cols-[440px_minmax(0,1fr)] xl:grid-cols-[505px_minmax(0,1fr)]" @if ($alpine) x-data="{{ $alpine }}" @endif>
        <aside class="relative isolate overflow-hidden bg-forest px-5 pt-5 pb-7 text-white sm:px-8 lg:flex lg:min-h-dvh lg:flex-col lg:px-16 lg:pt-14 lg:pb-12">
            <x-fingerprint class="absolute -top-10 -right-16 -z-10 w-[260px] text-accent/[0.22] lg:-top-24 lg:-right-36 lg:w-[460px]" />
            <x-fingerprint class="absolute -bottom-40 -left-32 -z-10 hidden w-[420px] rotate-[200deg] text-accent/[0.14] lg:block" />

            <p class="flex items-center gap-2.5 text-[11px] font-semibold tracking-[0.2em] text-[#cfe8d4] uppercase lg:text-[13px]">
                <span class="h-2 w-2 rounded-full bg-leaf" aria-hidden="true"></span> Renewed Hope Youth Engagement
            </p>

            <div class="mt-4 lg:mt-14">
                {{ $aside }}
            </div>

            @isset($asideFooter)
                <div class="mt-auto hidden pt-10 lg:block">{{ $asideFooter }}</div>
            @endisset

            @if ($stats)
                <dl class="mt-auto hidden grid-cols-3 gap-4 border-t border-white/15 pt-9 lg:grid">
                    @foreach ([['5,000+', 'youth empowered'], ['275+', 'events organised'], ['36+', 'states nationwide']] as [$n, $label])
                        <div>
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="font-headline text-[30px] leading-none font-bold">{{ $n }}</dd>
                            <dd class="mt-2 max-w-[7rem] text-[15px] leading-snug text-white/70">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </aside>

        <div class="relative isolate flex min-h-dvh flex-col overflow-hidden bg-white px-5 pt-5 pb-6 sm:px-8 lg:min-h-0 lg:px-14 lg:pt-9">
            {{-- Thumbprints: green on the white side, yellow on the green side. --}}
            <x-fingerprint class="pointer-events-none absolute -right-28 -bottom-36 -z-10 w-[300px] rotate-[25deg] text-brand/[0.10] sm:w-[420px] lg:w-[520px]" />
            <header class="flex items-center justify-between gap-4">
                <x-logo />
                <p class="text-right text-[15px] text-neutral-600">{{ $top ?? '' }}</p>
            </header>

            <main class="mx-auto w-full max-w-[550px] flex-1 pt-8 pb-12 sm:pt-12 lg:pt-16">
                {{ $slot }}
            </main>

            <footer class="flex flex-col gap-3 border-t border-line pt-5 text-sm text-neutral-600 sm:flex-row sm:items-center sm:justify-between sm:border-0 sm:pt-0">
                <p class="flex items-start gap-2"><x-icon name="lock" class="mt-0.5 h-4 w-4" /> Your details are protected under the Nigeria Data Protection Act 2023.</p>
                <p class="flex gap-6 font-medium">
                    <a href="{{ route('privacy') }}" class="hover:text-action">Privacy notice</a>
                    <a href="{{ config('membership.help_url') ?: route('privacy').'#your-rights' }}" class="hover:text-action">Get help</a>
                </p>
            </footer>
        </div>
    </div>
</x-layouts.base>
