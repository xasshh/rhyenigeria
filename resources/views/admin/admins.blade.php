@php use App\Support\NigerianPhone; @endphp
<x-layouts.app title="Admins" heading="Super admins">
    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_380px]">
        <section class="panel overflow-hidden">
            <ul class="divide-y divide-line">
                @foreach ($admins as $a)
                    <li class="flex flex-wrap items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $a->name }}
                                @if ($a->is(auth()->user()))<span class="badge bg-brand/10 text-brand-dark">You</span>@endif
                                @unless ($a->is_active)<span class="badge bg-red-50 text-red-700">Deactivated</span>@endunless
                            </p>
                            <p class="text-sm break-all text-neutral-600">{{ $a->email }}{{ $a->phone ? ' · '.NigerianPhone::format($a->phone) : '' }}</p>
                        </div>
                        @unless ($a->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.admins.update', $a) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $a->is_active ? 0 : 1 }}">
                                <button class="btn-secondary btn-sm {{ $a->is_active ? 'text-red-700' : '' }}">{{ $a->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </section>

        <form method="POST" action="{{ route('admin.admins.store') }}" class="panel space-y-4 self-start p-5">
            @csrf
            <h2 class="font-display text-lg font-bold">Add a super admin</h2>
            <p class="hint">Super admins see every member in every state and can change all settings, coordinators and admins. Only add people you trust fully.</p>
            <div><label class="label" for="a-name">Full name</label><input id="a-name" name="name" class="field" required value="{{ old('name') }}">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="a-email">Email (they sign in with this)</label><input id="a-email" name="email" type="email" class="field" required value="{{ old('email') }}">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="a-phone">Phone (optional)</label><input id="a-phone" name="phone" type="tel" class="field" value="{{ old('phone') }}">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="a-pass">Temporary password</label><input id="a-pass" name="password" type="password" class="field" required autocomplete="new-password" placeholder="At least 10 characters">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="a-pass2">Type it again</label><input id="a-pass2" name="password_confirmation" type="password" class="field" required autocomplete="new-password"></div>
            <p class="hint">Share the password privately and ask them to change it after signing in.</p>
            <button class="btn-primary w-full">Create super admin</button>
        </form>
    </div>
</x-layouts.app>
