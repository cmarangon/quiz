@php
    $tfOptions = collect($currentQuestion['options'] ?? [])
        ->map(fn ($option) => $option['label'] ?? $option)
        ->values()
        ->all();
    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;
    $themeBadge = $isThemed ? config('themes.'.$themeKey.'.badge') : null;
    $tfLetters = ['a', 'b'];
@endphp

@if($isThemed)
    <div
        wire:key="true-false-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="choiceAnswer()"
        class="qz-theme qz-theme--{{ $themeKey }} qz-player w-full"
    >
        @include('themes._deco')

        <div class="space-y-5">
            @if(! empty($currentQuestion['category_name']))
                <div class="flex justify-center">
                    <span class="qz-badge">{{ $themeBadge }} {{ $currentQuestion['category_name'] }}</span>
                </div>
            @endif

            @if($currentQuestion['body'] ?? null)
                <div class="qz-question">
                    <span class="qz-emoji">{{ $themeEmoji }}</span>
                    <h2>{{ $currentQuestion['body'] }}</h2>
                </div>
            @endif

            <div class="qz-options">
                @foreach($tfOptions as $index => $label)
                    <button
                        type="button"
                        x-on:click="choose(@js($label))"
                        x-bind:class="{ 'is-selected': selected === @js($label) }"
                        x-bind:disabled="submitted || (typeof expired !== 'undefined' && expired)"
                        data-test="player-answer-option"
                        data-answer-label="{{ $label }}"
                        class="qz-option {{ $tfLetters[$index % 2] }}"
                    >
                        <span class="qz-key">{{ strtoupper($tfLetters[$index % 2]) }}</span>
                        {{ __($label) }}
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                x-on:click="submit()"
                x-bind:disabled="selected === null || submitted || (typeof expired !== 'undefined' && expired)"
                data-test="true-false-submit"
                class="qz-cta"
            >
                {{ __('Submit answer') }}
            </button>
        </div>
    </div>
@else
    <div
        wire:key="true-false-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="choiceAnswer()"
        class="w-full space-y-4"
    >
        @if($currentQuestion['body'] ?? null)
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currentQuestion['body'] }}</h2>
        @endif

        @php
            $tfColors = ['bg-red-500 hover:bg-red-600', 'bg-blue-500 hover:bg-blue-600'];
        @endphp
        <div class="grid grid-cols-2 gap-3">
            @foreach($tfOptions as $index => $label)
                <button
                    type="button"
                    x-on:click="choose(@js($label))"
                    x-bind:class="selected === @js($label) ? 'ring-4 ring-offset-2 ring-zinc-900 dark:ring-white scale-105' : ''"
                    x-bind:disabled="submitted || (typeof expired !== 'undefined' && expired)"
                    data-test="player-answer-option"
                    data-answer-label="{{ $label }}"
                    class="rounded-xl p-8 text-lg font-bold text-white transition disabled:opacity-40 {{ $tfColors[$index % 2] }}"
                >
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        <button
            type="button"
            x-on:click="submit()"
            x-bind:disabled="selected === null || submitted || (typeof expired !== 'undefined' && expired)"
            data-test="true-false-submit"
            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-lg font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Submit answer') }}
        </button>
    </div>
@endif
