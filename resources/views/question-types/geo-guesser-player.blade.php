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
    $qzTheme = ($themeKey ?? null) && in_array($themeKey, array_keys(config('themes', [])), true) && $themeKey !== 'default'
        ? 'qz-theme qz-theme--'.$themeKey.' '
        : '';
@endphp

<div
    wire:key="geo-player-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
    x-data="geoMap(@js($geoConfig))"
    class="{{ $qzTheme }}w-full space-y-3"
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
