<div class="flex min-h-svh flex-col items-center justify-center p-8" @if($phase === 'lobby') wire:poll.2s="pollPlayers" @endif>
    {{-- LOBBY PHASE --}}
    @if($phase === 'lobby')
        <div class="text-center space-y-8">
            @if($quizTitle)
                <h1 class="text-5xl font-extrabold text-zinc-900 dark:text-white">{{ $quizTitle }}</h1>
            @endif

            <p class="text-xl text-zinc-500 dark:text-zinc-400">{{ __('Scan the QR code or enter the code below to join') }}</p>

            <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-12">
                {{-- Join code --}}
                <div class="rounded-2xl border-2 border-zinc-200 bg-white p-8 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Enter this code') }}</p>
                    <p class="font-mono text-7xl font-bold tracking-widest text-zinc-900 dark:text-white">
                        {{ $session->join_code }}
                    </p>
                </div>

                {{-- QR code --}}
                <div class="rounded-2xl border-2 border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            {{-- Player count --}}
            <p class="text-2xl text-zinc-600 dark:text-zinc-300">
                <span class="font-bold text-zinc-900 dark:text-white">{{ $playerCount }}</span>
                {{ trans_choice('{1} player|[2,*] players', $playerCount) }} {{ __('joined') }}
            </p>

            {{-- Player name list --}}
            @if(count($playerNames) > 0)
                <div class="flex flex-wrap justify-center gap-3">
                    @foreach($playerNames as $name)
                        <span class="rounded-full bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $name }}
                        </span>
                    @endforeach
                </div>
            @endif

            <p class="text-lg text-zinc-500 dark:text-zinc-400 animate-pulse">{{ __('Waiting for the host to start...') }}</p>
        </div>

    {{-- CATEGORY INTRO PHASE --}}
    @elseif($phase === 'category-intro')
        <div class="text-center space-y-6">
            @if($currentTheme)
                <div class="rounded-2xl bg-gradient-to-br {{ $currentTheme['gradient'] ?? '' }} p-12">
                    <p class="text-sm uppercase tracking-wider text-white/60">{{ __('Up Next') }}</p>
                    <h2 class="text-5xl font-bold text-white mt-2">{{ $currentTheme['name'] ?? '' }}</h2>
                </div>
            @endif
        </div>

    {{-- QUESTION PHASE --}}
    @elseif($phase === 'question')
        <div class="w-full max-w-4xl space-y-8">
            @if($currentQuestion)
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Question') }} {{ ($currentQuestion['question_index'] ?? 0) + 1 }}
                    </p>
                    <h2 class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">
                        {{ $currentQuestion['body'] ?? '' }}
                    </h2>
                </div>

                @if(! empty($currentQuestion['options']))
                    <div class="grid grid-cols-2 gap-4">
                        @foreach($currentQuestion['options'] as $index => $option)
                            <div class="rounded-xl border-2 border-zinc-200 dark:border-zinc-700 p-6 text-center text-xl font-semibold text-zinc-900 dark:text-white
                                @if($index === 0) bg-red-50 dark:bg-red-900/20
                                @elseif($index === 1) bg-blue-50 dark:bg-blue-900/20
                                @elseif($index === 2) bg-yellow-50 dark:bg-yellow-900/20
                                @else bg-green-50 dark:bg-green-900/20
                                @endif">
                                {{ $option['label'] ?? $option }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount, 'total' => $totalPlayers]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            @endif
        </div>

    {{-- REVIEW PHASE --}}
    @elseif($phase === 'review')
        <div class="w-full max-w-4xl space-y-8">
            @if($currentQuestion && ! empty($currentQuestion['options']))
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-zinc-900 dark:text-white">
                        {{ $currentQuestion['body'] ?? '' }}
                    </h2>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    @foreach($currentQuestion['options'] as $index => $option)
                        @php
                            $label = $option['label'] ?? $option;
                            $isCorrect = $label === $correctAnswer;
                        @endphp
                        <div class="rounded-xl border-2 p-6 text-center text-xl font-semibold
                            {{ $isCorrect
                                ? 'border-green-500 bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-200'
                                : 'border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500' }}">
                            {{ $label }}
                            @if($isCorrect)
                                <span class="ml-2">&#10003;</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(! empty($scores))
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">{{ __('Leaderboard') }}</h3>
                    <ol class="space-y-2">
                        @foreach($scores as $entry)
                            <li class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>{{ $entry['nickname'] ?? '' }}</span>
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>

    {{-- FINISHED PHASE --}}
    @elseif($phase === 'finished')
        <div class="w-full max-w-2xl space-y-8 text-center">
            <h1 class="text-4xl font-bold text-zinc-900 dark:text-white">{{ __('Game Over!') }}</h1>

            @if(! empty($leaderboard))
                {{-- Podium --}}
                <div class="flex items-end justify-center gap-4 mt-8">
                    @foreach(array_slice($leaderboard, 0, 3) as $index => $entry)
                        <div class="flex flex-col items-center">
                            <span class="text-lg font-bold text-zinc-900 dark:text-white">{{ $entry['nickname'] }}</span>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $entry['score'] }} {{ __('pts') }}</span>
                            <div class="mt-2 rounded-t-lg bg-gradient-to-t
                                @if($index === 0) from-yellow-500 to-yellow-300 w-24 h-32
                                @elseif($index === 1) from-zinc-400 to-zinc-300 w-20 h-24
                                @else from-amber-700 to-amber-500 w-20 h-16
                                @endif flex items-center justify-center">
                                <span class="text-2xl font-bold text-white">{{ $index + 1 }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Full leaderboard --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-left">
                    <ol class="space-y-2">
                        @foreach($leaderboard as $index => $entry)
                            <li class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>{{ $index + 1 }}. {{ $entry['nickname'] }}</span>
                                <span class="font-bold">{{ $entry['score'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    @endif
</div>
