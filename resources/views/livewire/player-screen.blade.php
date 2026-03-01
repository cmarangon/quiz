<div class="flex flex-col items-center gap-6 text-center">
    @if($player)
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
            {{ $player->nickname }}
        </h1>

        {{-- WAITING PHASE --}}
        @if($phase === 'waiting')
            <div class="space-y-4">
                <p class="text-zinc-500 dark:text-zinc-400 animate-pulse">
                    Waiting for the host to start the game...
                </p>
            </div>

        {{-- ANSWERING PHASE --}}
        @elseif($phase === 'answering')
            <div class="w-full max-w-md space-y-4">
                @if($currentQuestion && ! empty($currentQuestion['options']))
                    @php
                        $colors = ['bg-red-500 hover:bg-red-600', 'bg-blue-500 hover:bg-blue-600', 'bg-yellow-500 hover:bg-yellow-600', 'bg-green-500 hover:bg-green-600'];
                    @endphp
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($currentQuestion['options'] as $index => $option)
                            <button
                                wire:click="submitAnswer('{{ $option['label'] ?? $option }}')"
                                class="rounded-xl p-8 text-lg font-bold text-white transition {{ $colors[$index % 4] }}">
                                {{ $option['label'] ?? $option }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

        {{-- ANSWERED PHASE --}}
        @elseif($phase === 'answered')
            <div class="space-y-4">
                <div class="text-6xl">
                    @if($lastResult && $lastResult['is_correct'])
                        &#10003;
                    @else
                        &times;
                    @endif
                </div>
                <p class="text-zinc-500 dark:text-zinc-400">
                    Answer submitted, waiting for other players...
                </p>
            </div>

        {{-- REVIEW PHASE --}}
        @elseif($phase === 'review')
            <div class="space-y-4">
                @if($lastResult)
                    @if($lastResult['is_correct'])
                        <div class="rounded-xl bg-green-100 dark:bg-green-900/30 p-6">
                            <p class="text-2xl font-bold text-green-700 dark:text-green-300">Correct!</p>
                            <p class="text-green-600 dark:text-green-400">+{{ $lastResult['points_earned'] }} points</p>
                        </div>
                    @else
                        <div class="rounded-xl bg-red-100 dark:bg-red-900/30 p-6">
                            <p class="text-2xl font-bold text-red-700 dark:text-red-300">Wrong!</p>
                            <p class="text-red-600 dark:text-red-400">+0 points</p>
                        </div>
                    @endif
                @else
                    <p class="text-zinc-500 dark:text-zinc-400">No answer submitted</p>
                @endif
            </div>

        {{-- FINISHED PHASE --}}
        @elseif($phase === 'finished')
            <div class="w-full max-w-md space-y-6">
                <h2 class="text-3xl font-bold text-zinc-900 dark:text-white">Game Over!</h2>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Your Score</p>
                    <p class="text-4xl font-bold text-zinc-900 dark:text-white">{{ $player->fresh()->score }}</p>
                </div>

                @if(! empty($leaderboard))
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-left">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">Leaderboard</h3>
                        <ol class="space-y-2">
                            @foreach($leaderboard as $index => $entry)
                                <li class="flex justify-between
                                    {{ ($entry['nickname'] ?? '') === $player->nickname ? 'font-bold text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                                    <span>{{ $index + 1 }}. {{ $entry['nickname'] }}</span>
                                    <span>{{ $entry['score'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        @endif
    @else
        <div class="space-y-4">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Game</h1>
            <p class="text-zinc-500 dark:text-zinc-400">
                Could not find your player session.
            </p>
            <a href="{{ route('game.join', $session->join_code) }}"
               class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                Join Game
            </a>
        </div>
    @endif
</div>
