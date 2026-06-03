@php
    $letters = ['a', 'b', 'c', 'd'];
@endphp
<div class="qz-theme qz-theme--pop-culture qz-player" wire:key="player-answering-pop-culture-{{ $currentQuestion['question_id'] ?? '' }}">
    @include('themes.pop-culture._deco')

    <div class="space-y-5">
        @if(! empty($currentQuestion['category_name']))
            <div class="flex justify-center">
                <span class="qz-badge">★ {{ $currentQuestion['category_name'] }}</span>
            </div>
        @endif

        <div class="qz-options">
            @foreach($currentQuestion['options'] as $index => $option)
                @php $label = $option['label'] ?? $option; @endphp
                <button
                    wire:click="submitAnswer('{{ $label }}')"
                    data-test="player-answer-option"
                    data-answer-label="{{ $label }}"
                    class="qz-option {{ $letters[$index % 4] }}">
                    <span class="qz-key">{{ strtoupper($letters[$index % 4]) }}</span>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <p class="qz-hint">{{ __('Tap your answer before time runs out!') }}</p>
    </div>
</div>
