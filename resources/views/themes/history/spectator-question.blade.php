@php
    $letters = ['a', 'b', 'c', 'd'];
@endphp
<div class="qz-theme qz-theme--history qz-spectator w-full" wire:key="spectator-question-history-{{ $currentQuestion['question_id'] ?? '' }}">
    @include('themes.history._deco')

    <div class="mx-auto w-full max-w-4xl space-y-8">
        <div class="qz-question">
            <span class="qz-emoji">🏛️</span>
            <p class="qz-qlabel">{{ __('Question') }} {{ ($currentQuestion['question_index'] ?? 0) + 1 }}</p>
            <h2 data-test="spectator-question-body">{{ $currentQuestion['body'] ?? '' }}</h2>
        </div>

        @if(! empty($currentQuestion['options']))
            <div class="qz-options">
                @foreach($currentQuestion['options'] as $index => $option)
                    <div class="qz-option {{ $letters[$index % 4] }}">
                        <span class="qz-key">{{ strtoupper($letters[$index % 4]) }}</span>
                        {{ $option['label'] ?? $option }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="qz-meta">
            <span>{{ __(':answered / :total answered', ['answered' => $answeredCount, 'total' => $totalPlayers]) }}</span>
            <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
        </div>
    </div>
</div>
