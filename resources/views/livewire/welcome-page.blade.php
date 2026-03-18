<div>
    {{-- Top bar --}}
    <header class="fixed top-0 right-0 p-6 z-50">
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
            <a href="{{ route('login') }}">Admin Login</a>
        @endauth
    </header>

    {{-- Hero placeholder --}}
    <main class="flex flex-col items-center justify-center min-h-screen">
        <h1>{{ config('app.name', 'Quiz') }}</h1>
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
