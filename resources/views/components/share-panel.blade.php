@props(['shareLinks', 'inviteUrl', 'whatsappGroup' => null])
<div {{ $attributes->merge(['class' => 'panel p-4 sm:p-5']) }}>
    <h2 class="font-display text-base font-bold">Invite your friends</h2>
    <p class="mt-1 text-sm text-neutral-600">Your link carries your member number, so we know you brought them in.</p>
    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <a href="{{ $shareLinks['WhatsApp'] }}" target="_blank" rel="noopener" class="btn btn-sm bg-[#25d366] text-white hover:bg-[#1eb457]"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp</a>
        <a href="{{ $shareLinks['Facebook'] }}" target="_blank" rel="noopener" class="btn btn-sm bg-[#1877f2] text-white hover:bg-[#0f63d1]"><x-icon name="facebook" class="h-4 w-4" /> Facebook</a>
        <a href="{{ $shareLinks['X'] }}" target="_blank" rel="noopener" class="btn btn-sm bg-black text-white hover:bg-neutral-800"><x-icon name="x-logo" class="h-4 w-4" /> Post on X</a>
        <button type="button" x-data="copyText(@js($inviteUrl))" x-on:click="copy" class="btn-secondary btn-sm">
            <x-icon name="link" class="h-4 w-4" /> <span x-text="copied ? 'Copied!' : 'Copy link'">Copy link</span>
        </button>
    </div>
    <p class="mt-3 truncate rounded-lg bg-surface px-3 py-2 font-mono text-xs text-neutral-600">{{ $inviteUrl }}</p>
    @if ($whatsappGroup)
        <a href="{{ $whatsappGroup }}" target="_blank" rel="noopener" class="btn mt-3 w-full border border-[#25d366] bg-[#25d366]/10 text-[#128c4a] hover:bg-[#25d366]/20">
            <x-icon name="whatsapp" class="h-5 w-5" /> Join your state WhatsApp group
        </a>
    @endif
</div>
