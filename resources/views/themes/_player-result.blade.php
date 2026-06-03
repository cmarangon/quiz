@php($result = $lastResult ?? null)
@php($correct = $result['is_correct'] ?? false)
@php($points = $result['points_earned'] ?? 0)
<div class="qz-theme qz-theme--{{ $themeKey }} qz-player">
    @if($result)
        <div data-test="player-result" data-correct="{{ $correct ? '1' : '0' }}"
             class="qz-result {{ $correct ? 'qz-result--correct' : 'qz-result--wrong' }}">
            <div class="qz-result__reaction">{{ $correct ? '🎉' : '😬' }}</div>
            <div class="qz-result__title">{{ $correct ? __('Correct!') : __('Wrong!') }}</div>
            <div class="qz-result__points">+{{ $points }} {{ __('points') }}</div>
            @if($correct && ($player->streak ?? 0) > 1)
                <div class="qz-result__streak">🔥 {{ $player->streak }} {{ __('in a row') }}</div>
            @endif
        </div>
    @else
        <p class="text-zinc-400">{{ __('No answer submitted') }}</p>
    @endif
</div>
