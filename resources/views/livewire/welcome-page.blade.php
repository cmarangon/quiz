<div wire:poll.5s>
    {{-- Top bar --}}
    <header class="fixed top-0 right-0 p-6 z-50">
        @auth
            <a href="{{ route('dashboard') }}"
               class="px-5 py-2 rounded-full text-sm font-medium text-white backdrop-blur-md bg-white/10 border border-white/20 hover:bg-white/10 hover:border-white/20 hover:text-white hover:shadow-[0_0_15px_rgba(139,92,246,0.2)] transition-all duration-300">
                Dashboard
            </a>
        @else
            <a href="{{ route('login') }}"
               class="px-5 py-2 rounded-full text-sm font-medium text-white backdrop-blur-md bg-white/10 border border-white/20 hover:bg-white/10 hover:border-white/20 hover:text-white hover:shadow-[0_0_15px_rgba(139,92,246,0.2)] transition-all duration-300">
                Admin Login
            </a>
        @endauth
    </header>

    {{-- Hero --}}
    <main class="flex flex-col items-center justify-center min-h-screen pb-48 px-6">
        {{-- Logo --}}
        <div class="opacity-0 fade-in-up">
            <x-landing-logo class="w-32 h-32 lg:w-40 lg:h-40 drop-shadow-[0_0_30px_rgba(139,92,246,0.3)]" />
        </div>

        {{-- App name --}}
        <h1 class="mt-6 text-5xl lg:text-7xl font-bold bg-gradient-to-r from-purple-300 via-cyan-300 to-pink-300 bg-clip-text text-transparent opacity-0 fade-in-up-delay-1">
            {{ config('app.name', 'Quiz') }}
        </h1>

        {{-- Tagline --}}
        <p class="mt-4 text-lg text-zinc-300 opacity-0 fade-in-up-delay-2">
            Real-time party trivia
        </p>
    </main>

    {{-- Live Games Section --}}
    <section class="fixed bottom-0 inset-x-0 z-40 px-6 lg:px-8 pb-6 lg:pb-8">
        {{-- Section header --}}
        <div class="flex items-center gap-2 mb-4">
            @if($activeGames->isNotEmpty())
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
            @endif
            <h2 class="text-lg font-semibold text-white/90">Live Games</h2>
        </div>

        @if($activeGames->isEmpty())
            <div class="flex items-center justify-center py-8 rounded-2xl backdrop-blur-md bg-white/10 border border-white/20">
                <p class="text-zinc-400 text-sm">No live games right now</p>
            </div>
        @else
            <div class="flex gap-4 overflow-x-auto pt-8 -mt-8 pb-2 snap-x snap-mandatory scrollbar-hide">
                @foreach($activeGames as $game)
                    @php
                        $categoryType = $game->quiz->categories->first()?->type ?? 'default';
                        $theme = config("themes.{$categoryType}", config('themes.default'));
                    @endphp
                    <div class="flex-shrink-0 w-72 snap-start rounded-2xl backdrop-blur-md bg-white/10 border border-white/20 p-5 hover:bg-white/15 hover:border-white/30 hover:shadow-[0_0_20px_rgba(139,92,246,0.15)] hover:-translate-y-1 transition-all duration-300"
                         style="border-top: 2px solid;">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-white truncate mr-2">{{ $game->quiz->title }}</h3>
                            <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full
                                {{ $game->status === 'waiting' ? 'bg-emerald-500/20 text-emerald-400' : '' }}
                                {{ $game->status === 'playing' ? 'bg-amber-500/20 text-amber-400' : '' }}
                                {{ $game->status === 'reviewing' ? 'bg-blue-500/20 text-blue-400' : '' }}">
                                {{ $game->status === 'waiting' ? 'In Lobby' : ucfirst($game->status) }}
                            </span>
                        </div>

                        <div class="flex items-center text-sm text-zinc-300 mb-4">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $game->players->count() }} players
                        </div>

                        <a href="{{ route('game.spectator', $game->join_code) }}"
                           class="block w-full text-center px-4 py-2 rounded-xl text-sm font-medium bg-white/15 text-white border border-white/20 hover:bg-white/25 hover:text-white transition-all duration-200">
                            Watch
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
