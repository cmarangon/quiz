@php
    $style = $session->presentationStyle();
@endphp
<div class="flex min-h-svh flex-col items-center justify-center p-8" @if($phase === 'lobby') wire:poll.2s="pollPlayers" @endif>
    <div data-test="spectator-phase" data-phase="{{ $phase }}" class="hidden"></div>
    {{-- LOBBY PHASE --}}
    @if($phase === 'lobby')
        <div class="qz-stage qz-stage--{{ $style }} qz-lobby">
            <span class="qz-blob l1"></span><span class="qz-blob l2"></span><span class="qz-blob l3"></span>

            @if($quizTitle)
                <h1 class="qz-title">{{ $quizTitle }}</h1>
            @endif

            <p class="qz-lobby__subtitle">{{ __('Scan the QR code or enter the code below to join') }}</p>

            <div class="qz-lobby__join">
                {{-- Join code --}}
                <div class="qz-joincard">
                    <p class="qz-joincard__label">{{ __('Enter this code') }}</p>
                    <p class="qz-joincode">{{ $session->join_code }}</p>
                </div>

                {{-- QR code --}}
                <div class="qz-qrcard">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            {{-- Player count --}}
            <p class="qz-players">
                <span data-test="spectator-player-count" class="qz-players__count">{{ $playerCount }}</span>
                {{ trans_choice('{1} player|[2,*] players', $playerCount) }} {{ __('joined') }}
            </p>

            {{-- Player name list --}}
            @if(count($playerNames) > 0)
                <div class="qz-chips">
                    @foreach($playerNames as $name)
                        <x-player-name data-test="spectator-player-chip" data-player-nickname="{{ $name['nickname'] }}" :emoji="$name['emoji'] ?? null" :nickname="$name['nickname']" class="qz-chip" />
                    @endforeach
                </div>
            @endif

            <p class="qz-waiting">{{ __('Waiting for the host to start...') }}</p>

            {{-- Join link --}}
            <a href="{{ $joinUrl }}" data-test="spectator-link" class="qz-joinlink">
                {{ $joinUrl }}
            </a>
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
        @if($totalPlayers > 0 && $answeredCount >= $totalPlayers)
            <div wire:key="spectator-countdown-{{ $currentQuestion['question_id'] ?? 'na' }}"
                 x-data="{
                    remaining: {{ $countdownSeconds }},
                    timer: null,
                    init() {
                        this.timer = setInterval(() => {
                            this.remaining--;
                            if (this.remaining <= 0) clearInterval(this.timer);
                        }, 1000);
                    },
                    destroy() { clearInterval(this.timer); }
                 }"
                 data-test="spectator-countdown"
                 class="fixed inset-x-0 top-8 z-50 flex flex-col items-center gap-2">
                <p class="text-lg font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Everyone answered!') }}</p>
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-500 text-4xl font-bold text-white shadow-lg">
                    <span x-text="remaining" data-test="spectator-countdown-value">{{ $countdownSeconds }}</span>
                </div>
            </div>
        @endif
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports'], true))
            @include('themes.'.$themeKey.'.spectator-question')
        @elseif($currentQuestion)
            <div class="w-full max-w-4xl space-y-8">
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Question') }} {{ ($currentQuestion['question_index'] ?? 0) + 1 }}
                    </p>
                    <h2 data-test="spectator-question-body" class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">
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
                                @php $tfLabel = $option['label'] ?? $option; @endphp
                                {{ ($currentQuestion['type'] ?? null) === 'true_false' ? __($tfLabel) : $tfLabel }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount, 'total' => $totalPlayers]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            </div>
        @endif

    {{-- REVIEW PHASE --}}
    @elseif($phase === 'review')
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports'], true))
            @include('themes.'.$themeKey.'.spectator-review')
        @else
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
                            {{ ($currentQuestion['type'] ?? null) === 'true_false' ? __($label) : $label }}
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
                                <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname'] ?? ''" />
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
        @endif

    {{-- FINISHED PHASE --}}
    @elseif($phase === 'finished')
        <div class="w-full max-w-2xl space-y-8 text-center">
            <h1 class="text-4xl font-bold text-zinc-900 dark:text-white">{{ __('Game Over!') }}</h1>

            @if(! empty($leaderboard))
                {{-- Podium --}}
                <div class="flex items-end justify-center gap-4 mt-8">
                    @foreach(array_slice($leaderboard, 0, 3) as $index => $entry)
                        <div class="flex flex-col items-center">
                            <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" class="text-lg font-bold text-zinc-900 dark:text-white" />
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
                            <li data-test="spectator-leaderboard-row" data-player-nickname="{{ $entry['nickname'] }}" class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>{{ $index + 1 }}. <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" /></span>
                                <span class="font-bold">{{ $entry['score'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    @endif
</div>
