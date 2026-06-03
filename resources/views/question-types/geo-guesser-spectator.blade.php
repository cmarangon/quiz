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
    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;
@endphp

@if($isThemed)
    <div
        wire:key="geo-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        x-data="geoMap(@js($geoConfig))"
        class="qz-theme qz-theme--{{ $themeKey }} qz-spectator w-full"
    >
        @include('themes._deco')

        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div class="qz-question">
                <span class="qz-emoji">{{ $themeEmoji }}</span>
                <h2 data-test="spectator-question-body">{{ $currentQuestion['body'] ?? '' }}</h2>
            </div>

            <div class="qz-geoframe" wire:ignore>
                <div x-ref="map" data-test="geo-map"></div>
            </div>

            @if($phase === 'question')
                <p class="qz-hint">{{ __('Players are dropping their pins...') }}</p>

                <div class="qz-meta">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            @elseif($phase === 'review')
                <p class="qz-hint">{{ __('The correct location is marked in green.') }}</p>

                @if(! empty($scores ?? []))
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
            @endif
        </div>
    </div>
@else
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

            <div class="mx-auto flex w-full max-w-4xl items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
            </div>
        @elseif($phase === 'review')
            <p class="text-center text-green-600 dark:text-green-400 font-semibold">
                {{ __('The correct location is marked in green.') }}
            </p>

            @if(! empty($scores ?? []))
                <div class="mx-auto max-w-4xl rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">{{ __('Leaderboard') }}</h3>
                    <ol class="space-y-2">
                        @foreach($scores as $entry)
                            <li class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname'] ?? ''" />
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @endif
    </div>
@endif
