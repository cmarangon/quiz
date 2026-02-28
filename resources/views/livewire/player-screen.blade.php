<div class="flex flex-col items-center gap-6 text-center">
    @if($player)
        <div class="space-y-4">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                Welcome, {{ $player->nickname }}!
            </h1>

            @if($session->status === 'waiting')
                <p class="text-zinc-500 dark:text-zinc-400 animate-pulse">
                    Waiting for the host to start the game...
                </p>
            @elseif($session->status === 'playing')
                <p class="text-green-600 dark:text-green-400">
                    Game is in progress!
                </p>
            @elseif($session->status === 'finished')
                <p class="text-zinc-500 dark:text-zinc-400">
                    Game has ended. Your score: {{ $player->score }}
                </p>
            @endif
        </div>
    @else
        <div class="space-y-4">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Game</h1>
            <p class="text-zinc-500 dark:text-zinc-400">
                Could not find your player session.
            </p>
            <flux:button :href="route('game.join', $session->join_code)" variant="primary">
                Join Game
            </flux:button>
        </div>
    @endif
</div>
