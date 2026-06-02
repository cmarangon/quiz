@php
    $letters = ['a', 'b', 'c', 'd'];
@endphp
<div class="qz-theme qz-theme--sports qz-spectator w-full" wire:key="spectator-review-sports-{{ $currentQuestion['question_id'] ?? '' }}">
    @include('themes.sports._deco')

    <div class="mx-auto w-full max-w-4xl space-y-8">
        <div class="qz-question">
            <span class="qz-emoji">⚽</span>
            <h2>{{ $currentQuestion['body'] ?? '' }}</h2>
        </div>

        <div class="qz-options">
            @foreach($currentQuestion['options'] as $index => $option)
                @php
                    $label = $option['label'] ?? $option;
                    $isCorrect = $label === $correctAnswer;
                @endphp
                <div class="qz-option {{ $letters[$index % 4] }} {{ $isCorrect ? 'is-correct' : 'is-dim' }}">
                    <span class="qz-key">{{ strtoupper($letters[$index % 4]) }}</span>
                    {{ $label }}
                    @if($isCorrect)<span class="ml-auto">&#10003;</span>@endif
                </div>
            @endforeach
        </div>

        @if(! empty($scores))
            <div class="qz-question" style="text-align:left">
                <h3 class="qz-qlabel" style="margin-bottom:10px">{{ __('Leaderboard') }}</h3>
                <ol class="space-y-2">
                    @foreach($scores as $entry)
                        <li class="flex justify-between">
                            <span>{{ $entry['nickname'] ?? '' }}</span>
                            <span style="font-weight:700">{{ $entry['score'] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
</div>
