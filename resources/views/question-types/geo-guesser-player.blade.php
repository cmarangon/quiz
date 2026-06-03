@php
    $geoOptions = $currentQuestion['options'] ?? [];
    $geoCenter = $geoOptions['center'] ?? ['lat' => 20, 'lng' => 0];
    $geoZoom = $geoOptions['zoom'] ?? 2;
    $geoInteractive = $phase === 'answering';
    $geoCorrect = ($phase === 'review') ? $correctAnswer : null;
    $geoConfig = [
        'center' => $geoCenter,
        'zoom' => $geoZoom,
        'interactive' => $geoInteractive,
        'guess' => $lastGuess,
        'correct' => $geoCorrect,
    ];
    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;
    $themeBadge = $isThemed ? config('themes.'.$themeKey.'.badge') : null;
    $earned = $lastResult['points_earned'] ?? 0;
@endphp

@if($isThemed)
    <div
        wire:key="geo-player-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        x-data="geoMap(@js($geoConfig))"
        class="qz-theme qz-theme--{{ $themeKey }} qz-player w-full"
    >
        @include('themes._deco')

        <div class="space-y-4">
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

            <div class="qz-geoframe" wire:ignore>
                <div x-ref="map" data-test="geo-map"></div>
            </div>

            @if($geoInteractive)
                <p class="qz-hint" x-show="!guess">{{ __('Tap the map to drop your pin.') }}</p>
                <button
                    type="button"
                    x-on:click="submit()"
                    x-bind:disabled="!guess"
                    data-test="geo-submit"
                    class="qz-cta"
                >
                    {{ __('Submit guess') }}
                </button>
            @endif

            @if($phase === 'review' && $lastResult)
                <div class="qz-result {{ $earned > 0 ? 'qz-result--correct' : 'qz-result--wrong' }}">
                    <div class="qz-result__reaction">&#128205;</div>
                    @if($lastGuess)
                        <div class="qz-result__title">
                            <span x-text="distanceKm"></span> {{ __('km away') }}
                        </div>
                    @else
                        <div class="qz-result__title">{{ __('No guess submitted') }}</div>
                    @endif
                    <div class="qz-result__points">+{{ $earned }} {{ __('points') }}</div>
                </div>
            @endif
        </div>
    </div>
@else
    <div
        wire:key="geo-player-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        x-data="geoMap(@js($geoConfig))"
        class="w-full space-y-3"
    >
        @if($currentQuestion['body'] ?? null)
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currentQuestion['body'] }}</h2>
        @endif

        <div wire:ignore>
            <div
                x-ref="map"
                data-test="geo-map"
                class="h-72 w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700"
            ></div>
        </div>

        @if($geoInteractive)
            <p class="text-sm text-zinc-500 dark:text-zinc-400" x-show="!guess">
                {{ __('Tap the map to drop your pin.') }}
            </p>
            <button
                type="button"
                x-on:click="submit()"
                x-bind:disabled="!guess"
                data-test="geo-submit"
                class="w-full rounded-xl bg-blue-600 px-4 py-3 text-lg font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('Submit guess') }}
            </button>
        @endif

        @if($phase === 'review' && $lastResult)
            <div class="rounded-xl bg-zinc-100 p-4 dark:bg-zinc-800">
                @if($lastGuess)
                    <p class="text-zinc-700 dark:text-zinc-200">
                        {{ __('You were') }}
                        <span class="font-bold" x-text="distanceKm"></span>
                        {{ __('km away') }}
                    </p>
                @else
                    <p class="text-zinc-700 dark:text-zinc-200">{{ __('No guess submitted') }}</p>
                @endif
                <p class="font-bold text-green-600 dark:text-green-400">
                    +{{ $lastResult['points_earned'] }} {{ __('points') }}
                </p>
            </div>
        @endif
    </div>
@endif
