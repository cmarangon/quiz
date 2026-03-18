<div>
    {{-- Top bar --}}
    <header class="fixed top-0 right-0 p-6 z-50">
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
            <a href="{{ route('login') }}">Admin Login</a>
        @endauth
    </header>

    {{-- Hero --}}
    <main class="flex flex-col items-center justify-center min-h-screen px-6">
        {{-- Logo --}}
        <div class="opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_forwards]">
            <x-landing-logo class="w-32 h-32 lg:w-40 lg:h-40 drop-shadow-[0_0_30px_rgba(139,92,246,0.3)]" />
        </div>

        {{-- App name --}}
        <h1 class="mt-6 text-5xl lg:text-7xl font-bold bg-gradient-to-r from-purple-400 via-cyan-400 to-pink-400 bg-clip-text text-transparent opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_0.2s_forwards]">
            {{ config('app.name', 'Quiz') }}
        </h1>

        {{-- Tagline --}}
        <p class="mt-4 text-lg text-zinc-400 opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_0.4s_forwards]">
            Real-time party trivia
        </p>
    </main>

    {{-- Live games --}}
    <section class="px-6 pb-12">
        @if($activeGames->isEmpty())
            <p>No live games right now</p>
        @else
            @foreach($activeGames as $game)
                <div>
                    <span>{{ $game->quiz->title }}</span>
                    <span>{{ $game->players->count() }} players</span>
                    <a href="{{ route('game.spectator', $game->join_code) }}">Watch</a>
                </div>
            @endforeach
        @endif
    </section>
</div>
