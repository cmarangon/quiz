<div class="space-y-6" @if($phase === 'lobby') wire:poll.2s="pollPlayers" @endif>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Host Dashboard') }}</h1>
        <span data-test="host-phase" data-phase="{{ $phase }}" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium
            @if($phase === 'lobby') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
            @elseif($phase === 'playing') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
            @elseif($phase === 'reviewing') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
            @elseif($phase === 'finished') bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200
            @endif">
            {{ ucfirst($phase) }}
        </span>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Join Code') }}</p>
        <p data-test="join-code" class="font-mono text-3xl font-bold text-zinc-900 dark:text-white">{{ $session->join_code }}</p>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Share this code or the spectator link with your audience.') }}
        </p>
    </div>

    {{-- Spectator link --}}
    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Spectator Screen') }}</p>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Open this on a shared display so everyone can follow along.') }}
        </p>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex flex-col items-start gap-2">
                <flux:button
                    :href="$spectatorUrl"
                    target="_blank"
                    variant="primary"
                    icon="arrow-top-right-on-square"
                    data-test="spectator-link-button"
                >
                    {{ __('Open Spectator Screen') }}
                </flux:button>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"
                 data-test="spectator-qr-code">
                {!! $spectatorQrCodeSvg !!}
            </div>
        </div>
    </div>

    {{-- Answer Progress --}}
    @if($phase === 'playing')
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Answer Progress') }}</p>
            <div class="mt-2 flex items-center gap-3">
                <div class="flex-1 h-4 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                    <div class="h-full rounded-full bg-green-500 transition-all duration-300"
                         style="width: {{ $totalPlayers > 0 ? ($answeredCount / $totalPlayers) * 100 : 0 }}%"></div>
                </div>
                <span data-test="answer-progress" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ $answeredCount }} / {{ $totalPlayers }}
                </span>
            </div>

            @if($totalPlayers > 0 && $answeredCount >= $totalPlayers && $currentQuestionId)
                <div wire:key="auto-advance-{{ $currentQuestionId }}"
                     x-data="{
                        remaining: {{ $countdownSeconds }},
                        timer: null,
                        init() {
                            this.timer = setInterval(() => {
                                this.remaining--;
                                if (this.remaining <= 0) {
                                    clearInterval(this.timer);
                                    $wire.autoFinishQuestion({{ $currentQuestionId }});
                                }
                            }, 1000);
                        },
                        destroy() { clearInterval(this.timer); }
                     }"
                     data-test="host-auto-advance"
                     class="mt-3 text-sm font-medium text-green-700 dark:text-green-400">
                    {{ __('Everyone answered! Ending question in') }}
                    <span x-text="remaining"></span>{{ __('s...') }}
                </div>
            @endif
        </div>
    @endif

    <div>
        <h2 class="mb-3 text-lg font-semibold text-zinc-900 dark:text-white">
            {{ __('Players') }} ({{ $totalPlayers }})
        </h2>
        @if($players->isEmpty())
            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Waiting for players to join...') }}</p>
        @else
            <ul class="space-y-2">
                @foreach($players as $player)
                    <li data-test="host-player-row" data-player-nickname="{{ $player->nickname }}" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                        <x-player-name :emoji="$player->emoji" :nickname="$player->nickname" class="text-zinc-900 dark:text-white" />
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $player->score }} {{ __('pts') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="flex gap-3">
        @if($phase === 'lobby')
            <flux:button wire:click="startGame" variant="primary" data-test="host-start-game">
                {{ __('Start Game') }}
            </flux:button>
        @endif

        @if($phase === 'playing')
            <flux:button wire:click="finishQuestion" variant="primary" data-test="host-end-question">
                {{ __('End Question') }}
            </flux:button>
        @endif

        @if($phase === 'reviewing')
            <flux:button wire:click="nextQuestion" variant="primary" data-test="host-next-question">
                {{ __('Next Question') }}
            </flux:button>
        @endif

        @if($phase === 'finished')
            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Game has ended.') }}</p>
        @endif
    </div>
</div>
