<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Host Dashboard</h1>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium
            @if($session->status === 'waiting') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
            @elseif($session->status === 'playing') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
            @elseif($session->status === 'reviewing') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
            @else bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200
            @endif">
            {{ ucfirst($session->status) }}
        </span>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Join Code</p>
        <p class="font-mono text-3xl font-bold text-zinc-900 dark:text-white">{{ $session->join_code }}</p>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Share this code or the spectator link with your audience.
        </p>
    </div>

    <div>
        <h2 class="mb-3 text-lg font-semibold text-zinc-900 dark:text-white">
            Players ({{ $players->count() }})
        </h2>
        @if($players->isEmpty())
            <p class="text-zinc-500 dark:text-zinc-400">Waiting for players to join...</p>
        @else
            <ul class="space-y-2">
                @foreach($players as $player)
                    <li class="flex items-center gap-2 rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                        <span class="text-zinc-900 dark:text-white">{{ $player->nickname }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="flex gap-3">
        @if($session->status === 'waiting')
            <flux:button wire:click="startGame" variant="primary">
                Start Game
            </flux:button>
        @endif

        @if($session->status === 'reviewing')
            <flux:button wire:click="nextQuestion" variant="primary">
                Next Question
            </flux:button>
        @endif
    </div>
</div>
