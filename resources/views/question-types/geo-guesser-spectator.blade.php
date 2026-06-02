@php
    $geoOptions = $currentQuestion['options'] ?? [];
    $geoCenter = $geoOptions['center'] ?? ['lat' => 20, 'lng' => 0];
    $geoZoom = $geoOptions['zoom'] ?? 2;
    $geoCorrect = ($phase === 'review') ? $correctAnswer : null;
    $geoConfig = [
        'center' => $geoCenter,
        'zoom' => $geoZoom,
        'interactive' => false,
        'guess' => null,
        'correct' => $geoCorrect,
    ];
@endphp

<div
    wire:key="geo-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
    x-data="geoMap(@js($geoConfig))"
    class="w-full space-y-4"
>
    <div class="text-center">
        <h2 data-test="spectator-question-body" class="text-3xl font-bold text-zinc-900 dark:text-white">
            {{ $currentQuestion['body'] ?? '' }}
        </h2>
    </div>

    <div wire:ignore>
        <div
            x-ref="map"
            data-test="geo-map"
            class="h-[28rem] w-full overflow-hidden rounded-2xl border-2 border-zinc-200 dark:border-zinc-700"
        ></div>
    </div>

    @if($phase === 'question')
        <p class="text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Players are dropping their pins...') }}
        </p>
    @elseif($phase === 'review')
        <p class="text-center text-green-600 dark:text-green-400 font-semibold">
            {{ __('The correct location is marked in green.') }}
        </p>
    @endif
</div>
