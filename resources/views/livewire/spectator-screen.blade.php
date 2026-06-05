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
                <div class="rounded-3xl bg-gradient-to-br {{ $currentTheme['gradient'] ?? '' }} px-[clamp(3rem,8vw,8rem)] py-[clamp(3rem,7vw,7rem)]">
                    <p class="text-[clamp(1.75rem,3.4vw,3.5rem)] uppercase tracking-wider text-white/60">{{ __('Up Next') }}</p>
                    <h2 class="text-[clamp(4rem,10vw,12rem)] leading-none font-bold text-white mt-3">{{ $currentTheme['name'] ?? '' }}</h2>
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
                 class="fixed inset-x-0 top-8 z-50 flex flex-col items-center gap-4">
                <p class="text-[clamp(2rem,3.6vw,4rem)] font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Everyone answered!') }}</p>
                <div class="flex h-[clamp(7rem,12vw,16rem)] w-[clamp(7rem,12vw,16rem)] items-center justify-center rounded-full bg-green-500 text-[clamp(3.5rem,7vw,9rem)] font-bold text-white shadow-lg">
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
            <div class="w-full max-w-[96rem] space-y-6">
                <div class="text-center">
                    <p class="text-[clamp(1.6rem,3.2vw,3rem)] text-zinc-500 dark:text-zinc-400">
                        {{ __('Question') }} {{ ($currentQuestion['question_index'] ?? 0) + 1 }}
                    </p>
                    <h2 data-test="spectator-question-body" class="text-[clamp(3.25rem,7.5vw,8rem)] leading-tight font-bold text-zinc-900 dark:text-white mt-3">
                        {{ $currentQuestion['body'] ?? '' }}
                    </h2>
                </div>

                @if(! empty($currentQuestion['options']))
                    <div class="grid grid-cols-2 gap-8">
                        @foreach($currentQuestion['options'] as $index => $option)
                            <div class="rounded-2xl border-2 border-zinc-200 dark:border-zinc-700 p-12 text-center text-[clamp(2.25rem,4vw,4.5rem)] font-semibold text-zinc-900 dark:text-white
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

                <div class="flex items-center justify-between text-[clamp(1.5rem,2.8vw,2.75rem)] text-zinc-500 dark:text-zinc-400">
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
        <div class="w-full max-w-[96rem] space-y-6">
            @if($currentQuestion && ! empty($currentQuestion['options']))
                <div class="text-center">
                    <h2 class="text-[clamp(3.25rem,7.5vw,8rem)] leading-tight font-bold text-zinc-900 dark:text-white">
                        {{ $currentQuestion['body'] ?? '' }}
                    </h2>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    @foreach($currentQuestion['options'] as $index => $option)
                        @php
                            $label = $option['label'] ?? $option;
                            $isCorrect = $label === $correctAnswer;
                        @endphp
                        <div class="rounded-2xl border-2 p-12 text-center text-[clamp(2.25rem,4vw,4.5rem)] font-semibold
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
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-10">
                    <h3 class="text-[clamp(2rem,3.6vw,3.5rem)] font-semibold text-zinc-900 dark:text-white mb-6">{{ __('Leaderboard') }}</h3>
                    <ol class="space-y-5">
                        @foreach($scores as $entry)
                            <li class="flex justify-between text-[clamp(1.6rem,2.8vw,2.75rem)] text-zinc-700 dark:text-zinc-300">
                                <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname'] ?? ''" />
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
        @endif

    {{-- FINISHED PHASE — ULTRA THEMED RESULT --}}
    @elseif($phase === 'finished')
        @php($top = array_slice($leaderboard, 0, 3))
        @php($champion = $leaderboard[0] ?? null)
        <div class="qz-stage qz-stage--{{ $style }} qz-stage--has-bg qz-result">
            {{-- Searchlights sweeping the arena --}}
            <div class="qz-searchlights" aria-hidden="true">
                <span class="qz-searchlight s1"></span>
                <span class="qz-searchlight s2"></span>
                <span class="qz-searchlight s3"></span>
                <span class="qz-searchlight s4"></span>
            </div>

            {{-- Fireworks bursting overhead --}}
            <div class="qz-fireworks" aria-hidden="true">
                @for($i = 0; $i < 6; $i++)
                    <span class="qz-firework"
                          style="left: {{ [12, 31, 50, 69, 86, 44][$i] }}%; top: {{ [30, 16, 40, 20, 34, 12][$i] }}%; animation-delay: {{ $i * 0.5 }}s;"></span>
                @endfor
            </div>

            {{-- Confetti rain --}}
            <div class="qz-confetti" aria-hidden="true">
                @for($i = 0; $i < 40; $i++)
                    <i style="left: {{ ($i * 2.5) + 1 }}%; background: hsl({{ ($i * 47) % 360 }}, 90%, 60%); animation-duration: {{ 2.6 + (($i % 6) * 0.45) }}s; animation-delay: {{ ($i % 9) * 0.22 }}s;"></i>
                @endfor
            </div>

            {{-- Roaring fire across the base --}}
            <div class="qz-fire" aria-hidden="true">
                @for($i = 0; $i < 18; $i++)
                    <span class="qz-flame" style="animation-delay: {{ ($i % 5) * 0.11 }}s; --h: {{ 65 + (($i * 17) % 70) }}%;"></span>
                @endfor
            </div>

            <div class="qz-result__inner">
                <p class="qz-result__kicker">{{ __('And the champion is') }}</p>
                <h1 class="qz-result__title" data-test="spectator-game-over">{{ __('Game Over!') }}</h1>

                @if($champion)
                    <div class="qz-champion">
                        <span class="qz-champion__crown">👑</span>
                        <span class="qz-champion__emoji">{{ $champion['emoji'] ?? '🏆' }}</span>
                        <span class="qz-champion__name">{{ $champion['nickname'] }}</span>
                        <span class="qz-champion__score">{{ $champion['score'] }} {{ __('pts') }}</span>
                    </div>
                @endif

                @if(count($top) > 0)
                    <div class="qz-podium qz-podium--xl">
                        @php($order = [1 => $top[1] ?? null, 0 => $top[0] ?? null, 2 => $top[2] ?? null])
                        @foreach($order as $idx => $entry)
                            @if($entry)
                                <div class="qz-podium__col qz-podium__col--{{ $idx + 1 }}">
                                    <div class="qz-podium__emoji">{{ $entry['emoji'] ?? '🎮' }}</div>
                                    <div class="qz-podium__name">{{ $entry['nickname'] }}</div>
                                    <div class="qz-podium__score">{{ $entry['score'] }}</div>
                                    <div class="qz-podium__bar">{{ $idx + 1 }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if(! empty($leaderboard))
                    <div class="qz-board qz-board--xl">
                        @foreach($leaderboard as $index => $entry)
                            <div data-test="spectator-leaderboard-row" data-player-nickname="{{ $entry['nickname'] }}"
                                 class="qz-board__row" style="animation-delay: {{ $index * 0.07 }}s;">
                                <span class="qz-board__rank">{{ $index + 1 }}</span>
                                <span class="qz-board__name"><x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" /></span>
                                <span class="qz-board__score">{{ $entry['score'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
