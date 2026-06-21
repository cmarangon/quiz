@php
    $letters = ['a', 'b', 'c', 'd'];
@endphp
<div class="qz-theme qz-theme--history qz-player" wire:key="player-answering-history-{{ $currentQuestion['question_id'] ?? '' }}" x-data="choiceAnswer()">
    @include('themes.history._deco')

    <div class="space-y-5">
        @if(! empty($currentQuestion['category_name']))
            <div class="flex justify-center">
                <span class="qz-badge">⚜ {{ $currentQuestion['category_name'] }}</span>
            </div>
        @endif

        <div class="qz-options">
            @foreach($currentQuestion['options'] as $index => $option)
                @php $label = $option['label'] ?? $option; @endphp
                <button
                    type="button"
                    x-on:click="choose(@js($label))"
                    x-bind:class="{ 'is-selected': selected === @js($label) }"
                    x-bind:disabled="submitted || (typeof expired !== 'undefined' && expired)"
                    data-test="player-answer-option"
                    data-answer-label="{{ $label }}"
                    class="qz-option {{ $letters[$index % 4] }}">
                    <span class="qz-key">{{ strtoupper($letters[$index % 4]) }}</span>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button
            type="button"
            x-on:click="submit()"
            x-bind:disabled="selected === null || submitted || (typeof expired !== 'undefined' && expired)"
            data-test="multiple-choice-submit"
            class="qz-cta">
            {{ __('Submit answer') }}
        </button>
    </div>
</div>
