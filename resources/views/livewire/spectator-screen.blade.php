<div class="flex min-h-svh flex-col items-center justify-center p-8">
    <div class="text-center space-y-8">
        <h1 class="text-4xl font-bold text-zinc-900 dark:text-white">Join the Game!</h1>

        <div class="rounded-2xl border-2 border-zinc-200 bg-white p-8 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Enter this code</p>
            <p class="font-mono text-7xl font-bold tracking-widest text-zinc-900 dark:text-white">
                {{ $session->join_code }}
            </p>
        </div>

        <p class="text-2xl text-zinc-600 dark:text-zinc-300">
            <span class="font-bold text-zinc-900 dark:text-white">{{ $playerCount }}</span>
            {{ Str::plural('player', $playerCount) }} joined
        </p>

        @if($session->status === 'waiting')
            <p class="text-lg text-zinc-500 dark:text-zinc-400 animate-pulse">Waiting for the host to start...</p>
        @elseif($session->status === 'playing')
            <p class="text-lg text-green-600 dark:text-green-400">Game in progress!</p>
        @elseif($session->status === 'finished')
            <p class="text-lg text-zinc-500 dark:text-zinc-400">Game has ended.</p>
        @endif
    </div>
</div>
