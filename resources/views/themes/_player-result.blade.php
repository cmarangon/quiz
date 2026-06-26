@php($result = $lastResult ?? null)
@php($correct = $result['is_correct'] ?? false)
@php($points = $result['points_earned'] ?? 0)
@php($bd = $result['breakdown'] ?? null)
<div class="qz-theme qz-theme--{{ $themeKey }} qz-player">
    @if($result)
        <div data-test="player-result" data-correct="{{ $correct ? '1' : '0' }}"
             class="qz-result {{ $correct ? 'qz-result--correct' : 'qz-result--wrong' }}">
            <div class="qz-result__reaction">{{ $correct ? '🎉' : '😬' }}</div>
            <div class="qz-result__title">{{ $correct ? __('Correct!') : __('Wrong!') }}</div>
            <div class="qz-result__points">+{{ $points }} {{ __('points') }}</div>

            @if($bd && ($bd['time_bonus_enabled'] || $bd['streak_enabled']))
                {{-- How the score was reached: each row applies one factor to the
                     running subtotal, mirroring base × accuracy × speed × streak. --}}
                <div class="qz-breakdown" data-test="player-points-breakdown">
                    <div class="qz-breakdown__row">
                        <span class="qz-breakdown__label">
                            🎯 {{ __('Correct answer') }}@if($bd['accuracy'] < 1) <em>({{ round($bd['accuracy'] * 100) }}%)</em>@endif
                        </span>
                        <span class="qz-breakdown__value">{{ $bd['correct_points'] }}</span>
                    </div>

                    @if($bd['time_bonus_enabled'])
                        <div class="qz-breakdown__row">
                            <span class="qz-breakdown__label">
                                ⚡ {{ __('Speed') }} <em>{{ round($bd['time_factor'] * 100) }}% {{ __('time left') }}</em>
                            </span>
                            <span class="qz-breakdown__value">
                                <span class="qz-breakdown__mult">×{{ rtrim(rtrim(number_format($bd['time_factor'], 2), '0'), '.') }}</span>
                                {{ $bd['speed_points'] }}
                            </span>
                        </div>
                    @endif

                    @if($bd['streak_multiplier'] > 1)
                        <div class="qz-breakdown__row">
                            <span class="qz-breakdown__label">🔥 {{ __('Streak') }}</span>
                            <span class="qz-breakdown__value">
                                <span class="qz-breakdown__mult">×{{ rtrim(rtrim(number_format($bd['streak_multiplier'], 1), '0'), '.') }}</span>
                                {{ $bd['total'] }}
                            </span>
                        </div>
                    @endif

                    <div class="qz-breakdown__row qz-breakdown__row--total">
                        <span class="qz-breakdown__label">{{ __('Total') }}</span>
                        <span class="qz-breakdown__value">+{{ $bd['total'] }}</span>
                    </div>
                </div>
            @endif

            @if($correct && ($player->streak ?? 0) > 1)
                <div class="qz-result__streak">🔥 {{ $player->streak }} {{ __('in a row') }}</div>
            @endif
        </div>
    @else
        <p class="text-zinc-400">{{ __('No answer submitted') }}</p>
    @endif
</div>
