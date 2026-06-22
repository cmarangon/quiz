@php
    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;

    $displayItems = collect($currentQuestion['options'] ?? [])
        ->map(fn ($option) => $option['label'] ?? $option)
        ->values()
        ->all();
    $correctOrder = (($phase ?? null) === 'review' && is_array($correctAnswer ?? null))
        ? array_values($correctAnswer)
        : [];
    $letters = ['a', 'b', 'c', 'd'];
@endphp

@if($isThemed)
    <div
        wire:key="ordering-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="qz-theme qz-theme--{{ $themeKey }} qz-spectator {{ ($phase ?? null) === 'review' ? 'qz-review' : '' }} w-full"
    >
        @include('themes._deco')

        <div class="mx-auto w-full space-y-8">
            <div class="qz-question">
                <span class="qz-emoji">{{ $themeEmoji }}</span>
                <h2 data-test="spectator-question-body">{{ $currentQuestion['body'] ?? '' }}</h2>
            </div>

            @if(($phase ?? null) === 'review')
                <ol class="qz-orderlist" data-test="ordering-correct-order">
                    @foreach($correctOrder as $index => $label)
                        <li class="qz-option qz-order is-correct {{ $letters[$index % 4] }}">
                            <span class="qz-key">{{ $index + 1 }}</span>
                            <span class="qz-order__label">{{ $label }}</span>
                            <span class="ml-auto">&#10003;</span>
                        </li>
                    @endforeach
                </ol>

                @if(($showScoreboard ?? true) && ! empty($scores ?? []))
                    <div class="qz-question mx-auto max-w-3xl" style="text-align:left">
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
            @else
                <ul class="qz-orderlist">
                    @foreach($displayItems as $index => $label)
                        <li class="qz-option qz-order {{ $letters[$index % 4] }}">
                            <span class="qz-key">{{ $index + 1 }}</span>
                            <span class="qz-order__label">{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="qz-hint">{{ __('Players are arranging the items...') }}</p>

                <div class="qz-meta">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            @endif
        </div>
    </div>
@else
    <div
        wire:key="ordering-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="w-full space-y-8"
    >
        <div class="text-center">
            <h2 data-test="spectator-question-body" class="text-[clamp(2.5rem,5vw,4.5rem)] leading-tight font-bold text-zinc-900 dark:text-white">
                {{ $currentQuestion['body'] ?? '' }}
            </h2>
        </div>

        @if(($phase ?? null) === 'review')
            <p class="text-center text-3xl font-semibold text-green-600 dark:text-green-400">
                {{ __('Correct order:') }}
            </p>
            <ol class="mx-auto max-w-3xl space-y-4" data-test="ordering-correct-order">
                @foreach($correctOrder as $index => $label)
                    <li class="flex items-center gap-4 rounded-xl border-2 border-green-500 bg-green-100 p-6 text-3xl font-semibold text-green-900 dark:bg-green-900/40 dark:text-green-200">
                        <span class="text-green-600 dark:text-green-400">{{ $index + 1 }}.</span>
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ol>

            @if(($showScoreboard ?? true) && ! empty($scores ?? []))
                <div class="mx-auto max-w-3xl rounded-xl border border-zinc-200 dark:border-zinc-700 p-8">
                    <h3 class="text-3xl font-semibold text-zinc-900 dark:text-white mb-6">{{ __('Leaderboard') }}</h3>
                    <ol class="space-y-4">
                        @foreach($scores as $entry)
                            <li class="flex justify-between text-2xl text-zinc-700 dark:text-zinc-300">
                                <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname'] ?? ''" />
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @else
            <ul class="mx-auto max-w-3xl space-y-4">
                @foreach($displayItems as $label)
                    <li class="rounded-xl border-2 border-zinc-200 bg-white p-6 text-center text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        {{ $label }}
                    </li>
                @endforeach
            </ul>
            <p class="text-center text-2xl text-zinc-500 dark:text-zinc-400">
                {{ __('Players are arranging the items...') }}
            </p>

            <div class="mx-auto flex max-w-3xl items-center justify-between text-2xl text-zinc-500 dark:text-zinc-400">
                <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
            </div>
        @endif
    </div>
@endif
