@php
    $style = $session->presentationStyle();
@endphp
<div class="flex flex-col items-center gap-6 text-center" @if(in_array($phase, ['waiting', 'answered', 'review'], true)) wire:poll.2s="pollState" @endif>
    @if($player)
        <h1 data-test="player-nickname" data-player-nickname="{{ $player->nickname }}" class="text-2xl font-bold text-zinc-900 dark:text-white">
            <x-player-name :emoji="$player->emoji" :nickname="$player->nickname" />
        </h1>
        <div data-test="player-phase" data-phase="{{ $phase }}" class="hidden"></div>

        {{-- WAITING PHASE --}}
        @if($phase === 'waiting')
            <div class="qz-stage qz-stage--{{ $style }} qz-stage--has-bg w-full max-w-md">
                <div class="qz-card">
                    @if($style === 'party-pop')
                        <span class="qz-blob b1"></span><span class="qz-blob b2"></span>
                    @elseif($style === 'game-show')
                        <span class="qz-blob spot"></span>
                    @else
                        <span class="qz-blob u1"></span><span class="qz-blob u2"></span>
                    @endif

                    <div class="qz-waiting-emoji">🎉</div>
                    <h2 class="qz-title">{{ __("You're in!") }}</h2>
                    <p class="qz-waiting">{{ __('Waiting for the host to start the game...') }}</p>
                    <div class="qz-loader" aria-hidden="true"><span></span><span></span><span></span></div>
                </div>
            </div>

        {{-- ANSWERING PHASE --}}
        @elseif($phase === 'answering')
            <div class="w-full max-w-md space-y-4"
                 wire:key="qtimer-{{ $currentQuestion['question_id'] ?? 'q' }}"
                 @answer-provider="answerProvider = $event.detail.provider"
                 x-data="questionTimer({ limit: {{ (int) ($currentQuestion['time_limit_seconds'] ?? 30) }}, startedAt: {{ $currentQuestion['started_at'] ?? 'null' }} })">

                {{-- Cartoony countdown: grows bigger and redder as time runs out. --}}
                <div class="qz-timer"
                     data-test="question-timer"
                     aria-hidden="true"
                     x-show="!expired"
                     x-bind:class="{ 'qz-timer--urgent': fraction > 0.6 && !expired, 'qz-timer--panic': fraction > 0.85 && !expired }"
                     x-bind:style="'--t:' + fraction">
                    <span class="qz-timer__num" x-text="remaining"></span>
                </div>
                <div class="qz-timer qz-timer--done" aria-hidden="true" x-show="expired">
                    <span class="qz-timer__num">⏰</span>
                </div>

                @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
                    @include('question-types.geo-guesser-player')
                @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
                    @include('question-types.ordering-player')
                @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'true_false')
                    @include('question-types.true-false-player')
                @elseif($currentQuestion && ! empty($currentQuestion['options']))
                    @if(in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'], true))
                        @include('themes.'.$themeKey.'.player-answering')
                    @else
                    @php
                        $colors = ['bg-red-500 hover:bg-red-600', 'bg-blue-500 hover:bg-blue-600', 'bg-yellow-500 hover:bg-yellow-600', 'bg-green-500 hover:bg-green-600'];
                    @endphp
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($currentQuestion['options'] as $index => $option)
                            <button
                                wire:click="submitAnswer('{{ $option['label'] ?? $option }}')"
                                x-bind:disabled="expired"
                                data-test="player-answer-option"
                                data-answer-label="{{ $option['label'] ?? $option }}"
                                class="rounded-xl p-8 text-lg font-bold text-white transition disabled:opacity-40 {{ $colors[$index % 4] }}">
                                {{ $option['label'] ?? $option }}
                            </button>
                        @endforeach
                    </div>
                    @endif
                @endif
            </div>

        {{-- ANSWERED PHASE --}}
        @elseif($phase === 'answered')
            <div class="qz-stage qz-stage--{{ $style }} qz-stage--has-bg w-full max-w-md">
                <div class="qz-card">
                    @if($style === 'party-pop')
                        <span class="qz-blob b1"></span><span class="qz-blob b2"></span>
                    @elseif($style === 'game-show')
                        <span class="qz-blob spot"></span>
                    @else
                        <span class="qz-blob u1"></span><span class="qz-blob u2"></span>
                    @endif

                    @if($timedOut)
                        <div class="qz-waiting-emoji">&#9200;</div>
                        <p class="qz-waiting">{{ __("Time's up! You ran out of time this round.") }}</p>
                    @else
                        <div class="qz-waiting-emoji">&#128076;</div>
                        <p class="qz-waiting">{{ __('Answer submitted, waiting for other players...') }}</p>
                    @endif
                    <div class="qz-loader" aria-hidden="true"><span></span><span></span><span></span></div>
                </div>
            </div>

        {{-- REVIEW PHASE --}}
        @elseif($phase === 'review')
            @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
                <div class="w-full max-w-md space-y-4">
                    @include('question-types.geo-guesser-player')
                </div>
            @else
                <div class="w-full max-w-md">
                    @include('themes._player-result')
                </div>
            @endif

            {{-- Reaction bar: tap an emoji to float it across the spectator screen.
                 Throttled client-side; validated + broadcast server-side. --}}
            <div class="mt-2 flex flex-wrap items-center justify-center gap-3"
                 x-data="reactionBar" aria-label="{{ __('Reactions') }}">
                @foreach(config('reactions.emojis') as $emoji)
                    <button type="button"
                            @click="react('{{ $emoji }}')"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-white/80 text-3xl shadow transition active:scale-90 dark:bg-zinc-800/80"
                            aria-label="{{ __('Send reaction') }} {{ $emoji }}">
                        {{ $emoji }}
                    </button>
                @endforeach
            </div>

        {{-- FINISHED PHASE --}}
        @elseif($phase === 'finished')
            @php($top = array_slice($leaderboard, 0, 3))
            <div class="qz-stage qz-stage--{{ $style }} w-full max-w-md">
                <div class="qz-confetti" aria-hidden="true">
                    @for($i = 0; $i < 24; $i++)
                        <i style="left: {{ ($i * 4.1) + 2 }}%; background: hsl({{ ($i * 37) % 360 }}, 85%, 60%); animation-duration: {{ 2.4 + (($i % 5) * 0.4) }}s; animation-delay: {{ ($i % 7) * 0.18 }}s;"></i>
                    @endfor
                </div>

                <div class="qz-card">
                    <div class="qz-final">
                        <h2 class="qz-final__title">{{ __('Game Over!') }}</h2>

                        @if(count($top) > 0)
                            <div class="qz-podium">
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

                        @php($finalScore = $player->fresh()->score)
                        <div class="qz-scorecard">
                            <div class="qz-scorecard__label">{{ __('Your Score') }}</div>
                            {{-- Alpine count-up; server-rendered fallback text keeps the real
                                 number present for Pest/non-JS, while browsers animate 0 → score. --}}
                            <div class="qz-scorecard__value" data-test="player-final-score"
                                 x-data="{ n: 0, target: {{ $finalScore }} }"
                                 x-init="let step = Math.max(1, Math.round(target / 40)); let t = setInterval(() => { n = Math.min(target, n + step); if (n >= target) clearInterval(t); }, 25)"
                                 x-text="n">{{ $finalScore }}</div>
                        </div>

                        @if(! empty($leaderboard))
                            <div class="qz-board">
                                @foreach($leaderboard as $index => $entry)
                                    <div @class(['qz-board__row', 'is-me' => ($entry['nickname'] ?? '') === $player->nickname])>
                                        <span>{{ $index + 1 }}. <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" /></span>
                                        <span>{{ $entry['score'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="space-y-4">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Game') }}</h1>
            <p class="text-zinc-500 dark:text-zinc-400">
                {{ __('Could not find your player session.') }}
            </p>
            <a href="{{ route('game.join', $session->join_code) }}"
               class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                {{ __('Join Game') }}
            </a>
        </div>
    @endif
</div>
