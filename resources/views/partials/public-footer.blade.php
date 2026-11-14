    <footer id="contact" class="bg-forest text-white">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-6 px-5 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-[66px]">
            <div>
                <p class="font-headline text-[26px] font-extrabold tracking-[-0.02em] text-mint">{{ $slogan }}</p>
                <p class="mt-1 font-body text-white/70">Renewed Hope Youth Engagement · Youth for PBAT</p>
            </div>
            <div>
                <p class="font-body text-sm font-semibold tracking-[0.14em] text-white/60 uppercase">Follow us</p>
                <x-social-links class="mt-3" />
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 font-body text-[15px] text-white/80" aria-label="Footer">
                <a href="{{ route('news.index') }}" class="hover:text-white">News</a>
                <a href="{{ route('join') }}" class="hover:text-white">Join the movement</a>
                <a href="{{ route('login') }}" class="hover:text-white">Sign in</a>
                <a href="{{ route('privacy') }}" class="hover:text-white">Privacy notice</a>
            </nav>
        </div>
    </footer>
