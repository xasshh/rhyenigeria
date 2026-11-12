@php use App\Support\NigerianPhone; use App\Support\Mask; $tz = config('membership.timezone'); @endphp
<x-layouts.app :title="$member->name">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : staff_route('members.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand"><x-icon name="arrow-left" class="h-4 w-4" /> Members</a>
    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="font-display text-2xl font-bold tracking-tight">{{ $member->name }}</h1>
        <span class="badge bg-brand/10 text-brand-dark">{{ $profile->roleLabel() }}</span>
        @unless ($member->is_active)<span class="badge bg-red-50 text-red-700">Deactivated</span>@endunless
    </div>
    <p class="font-mono text-sm text-neutral-500">{{ $profile->member_number }}</p>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <section class="panel p-5">
            <h2 class="font-display font-bold">Contact & profile</h2>
            <dl class="mt-3 grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2 text-sm">
                <dt class="text-neutral-500">Phone</dt><dd class="font-medium"><a href="tel:{{ $member->phone }}" class="text-brand-dark">{{ NigerianPhone::format($member->phone) }}</a></dd>
                @if ($profile->whatsapp_phone)<dt class="text-neutral-500">WhatsApp</dt><dd><a class="font-medium text-brand-dark" href="https://wa.me/{{ ltrim($profile->whatsapp_phone, '+') }}" target="_blank" rel="noopener">{{ NigerianPhone::format($profile->whatsapp_phone) }}</a></dd>@endif
                <dt class="text-neutral-500">Email</dt><dd class="font-medium break-all">{{ $member->email }}</dd>
                <dt class="text-neutral-500">Gender / age</dt><dd>{{ $profile->gender?->label() }}, {{ $profile->age_range?->label() }}</dd>
                <dt class="text-neutral-500">Occupation</dt><dd>{{ $profile->occupation ?: '—' }}</dd>
                <dt class="text-neutral-500">Joined</dt><dd>{{ $profile->created_at->setTimezone($tz)->format('j M Y, g:ia') }}</dd>
                <dt class="text-neutral-500">Invited by</dt><dd>{{ $profile->referredBy ? $profile->referredBy->name.' ('.$profile->referredBy->memberProfile?->member_number.')' : '—' }}</dd>
                <dt class="text-neutral-500">Has invited</dt><dd>{{ $referrals }} {{ str('member')->plural($referrals) }}</dd>
            </dl>
        </section>
        <section class="panel p-5">
            <h2 class="font-display font-bold">Location & voter details</h2>
            <dl class="mt-3 grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2 text-sm">
                <dt class="text-neutral-500">State</dt><dd>{{ $member->state?->name }}</dd>
                <dt class="text-neutral-500">LGA</dt><dd>{{ $member->lga?->name ?? '—' }}</dd>
                <dt class="text-neutral-500">Ward</dt><dd>{{ $member->ward?->name ?? '—' }}</dd>
                <dt class="text-neutral-500">Polling unit</dt><dd>{{ $profile->pollingUnit ? $profile->pollingUnit->code.' · '.$profile->pollingUnit->name : 'Not sure' }}</dd>
                <dt class="text-neutral-500">PVC</dt><dd>{{ $profile->has_pvc ? 'Yes' : 'Not yet' }}</dd>
                <dt class="text-neutral-500">VIN</dt><dd class="font-mono">{{ Mask::identifier($profile->vin) }}</dd>
                <dt class="text-neutral-500">NIN</dt><dd class="font-mono">{{ Mask::identifier($profile->nin) }}</dd>
                <dt class="text-neutral-500">Signup GPS</dt><dd>@if ($profile->signup_lat !== null)<a class="text-brand-dark underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $profile->signup_lat }}&mlon={{ $profile->signup_lng }}#map=16/{{ $profile->signup_lat }}/{{ $profile->signup_lng }}">{{ $profile->signup_lat }}, {{ $profile->signup_lng }}</a>@else Not shared @endif</dd>
            </dl>
        </section>
    </div>

    @if ($profile->canvasser_status)
        <section class="panel mt-4 p-5">
            <h2 class="font-display font-bold">Canvasser application</h2>
            @include('staff.partials.canvasser-review', ['m' => $member, 'p' => $profile, 'canReview' => $canReview])
        </section>
    @endif

    @if ($member->empowermentReports->isNotEmpty())
        <section class="panel mt-4 p-5">
            <h2 class="font-display font-bold">Empowerment</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($member->empowermentReports as $r)
                    <div class="flex gap-3 rounded-xl bg-surface p-3">
                        @if ($r->photo_path)<a href="{{ route('empowerment.photo', $r) }}" target="_blank"><img src="{{ route('empowerment.photo', $r) }}" alt="" class="h-20 w-20 rounded-lg object-cover" loading="lazy"></a>@endif
                        <div class="text-sm"><p class="font-semibold">{{ $r->programme }}</p><p class="text-neutral-500">{{ $r->received_on?->format('M Y') ?? 'Date not given' }}</p><p>{{ $r->what_received }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
