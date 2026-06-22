@php
    $resolveItems = fn ($items) => collect($items)->map(fn ($item) => [
        'kind' => $item['kind'] ?? 'text',
        'value' => ($item['kind'] ?? 'text') === 'image'
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['value'])
            : $item['value'],
    ])->values()->all();

    $leftItems = $resolveItems($currentQuestion['options']['left'] ?? []);
    $rightItems = $resolveItems($currentQuestion['options']['right'] ?? []);

    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;

    $correctPairs = [];
    if (($phase ?? null) === 'review' && is_array($correctAnswer ?? null)) {
        foreach ($correctAnswer as $leftIndex => $rightIndex) {
            $correctPairs[] = ['left' => $leftItems[$leftIndex] ?? null, 'right' => $rightItems[$rightIndex] ?? null];
        }
    }
@endphp

@if($isThemed)
    <div
        wire:key="match-pairs-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="qz-theme qz-theme--{{ $themeKey }} qz-spectator {{ ($phase ?? null) === 'review' ? 'qz-review' : '' }} w-full"
    >
        @include('themes._deco')

        <div class="mx-auto w-full space-y-8">
            <div class="qz-question">
                <span class="qz-emoji">{{ $themeEmoji }}</span>
                <h2 data-test="spectator-question-body">{{ $currentQuestion['body'] ?? '' }}</h2>
            </div>

            @if(($phase ?? null) === 'review')
                <div class="space-y-3" data-test="match-pairs-correct-pairs">
                    @foreach($correctPairs as $pair)
                        <div class="flex items-center justify-center gap-4">
                            <span class="qz-option qz-order is-correct">
                                @if(($pair['left']['kind'] ?? null) === 'image')
                                    <img src="{{ $pair['left']['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $pair['left']['value'] ?? '' }}</span>
                                @endif
                            </span>
                            <span aria-hidden="true">&#8596;</span>
                            <span class="qz-option qz-order is-correct">
                                @if(($pair['right']['kind'] ?? null) === 'image')
                                    <img src="{{ $pair['right']['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $pair['right']['value'] ?? '' }}</span>
                                @endif
                            </span>
                            <span class="ml-auto">&#10003;</span>
                        </div>
                    @endforeach
                </div>

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
                <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
                    <ul class="qz-orderlist">
                        @foreach($leftItems as $item)
                            <li class="qz-option qz-order">
                                @if($item['kind'] === 'image')
                                    <img src="{{ $item['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $item['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <ul class="qz-orderlist">
                        @foreach($rightItems as $item)
                            <li class="qz-option qz-order">
                                @if($item['kind'] === 'image')
                                    <img src="{{ $item['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $item['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="qz-hint">{{ __('Players are matching pairs...') }}</p>

                <div class="qz-meta">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            @endif
        </div>
    </div>
@else
    <div
        wire:key="match-pairs-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="w-full space-y-8"
    >
        <div class="text-center">
            <h2 data-test="spectator-question-body" class="text-[clamp(2.5rem,5vw,4.5rem)] leading-tight font-bold text-zinc-900 dark:text-white">
                {{ $currentQuestion['body'] ?? '' }}
            </h2>
        </div>

        @if(($phase ?? null) === 'review')
            <p class="text-center text-3xl font-semibold text-green-600 dark:text-green-400">
                {{ __('Correct pairs:') }}
            </p>
            <div class="mx-auto max-w-3xl space-y-4" data-test="match-pairs-correct-pairs">
                @foreach($correctPairs as $pair)
                    <div class="flex items-center justify-center gap-4 rounded-xl border-2 border-green-500 bg-green-100 p-6 dark:bg-green-900/40">
                        <span class="flex items-center justify-center text-2xl font-semibold text-green-900 dark:text-green-200">
                            @if(($pair['left']['kind'] ?? null) === 'image')
                                <img src="{{ $pair['left']['value'] }}" class="h-20 w-20 rounded object-cover" alt="" />
                            @else
                                {{ $pair['left']['value'] ?? '' }}
                            @endif
                        </span>
                        <span class="text-green-600 dark:text-green-400" aria-hidden="true">&#8596;</span>
                        <span class="flex items-center justify-center text-2xl font-semibold text-green-900 dark:text-green-200">
                            @if(($pair['right']['kind'] ?? null) === 'image')
                                <img src="{{ $pair['right']['value'] }}" class="h-20 w-20 rounded object-cover" alt="" />
                            @else
                                {{ $pair['right']['value'] ?? '' }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

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
            <div class="mx-auto grid max-w-4xl grid-cols-2 gap-4" data-test="match-pairs-columns">
                <ul class="space-y-4">
                    @foreach($leftItems as $item)
                        <li class="flex items-center justify-center rounded-xl border-2 border-zinc-200 bg-white p-6 text-center text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @if($item['kind'] === 'image')
                                <img src="{{ $item['value'] }}" class="h-24 w-24 rounded object-cover" alt="" />
                            @else
                                {{ $item['value'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <ul class="space-y-4">
                    @foreach($rightItems as $item)
                        <li class="flex items-center justify-center rounded-xl border-2 border-zinc-200 bg-white p-6 text-center text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @if($item['kind'] === 'image')
                                <img src="{{ $item['value'] }}" class="h-24 w-24 rounded object-cover" alt="" />
                            @else
                                {{ $item['value'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-center text-2xl text-zinc-500 dark:text-zinc-400">
                {{ __('Players are matching pairs...') }}
            </p>

            <div class="mx-auto flex max-w-3xl items-center justify-between text-2xl text-zinc-500 dark:text-zinc-400">
                <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
            </div>
        @endif
    </div>
@endif
