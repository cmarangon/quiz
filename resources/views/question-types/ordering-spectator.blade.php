@php
    $qzTheme = ($themeKey ?? null) && in_array($themeKey, array_keys(config('themes', [])), true) && $themeKey !== 'default'
        ? 'qz-theme qz-theme--'.$themeKey.' '
        : '';
@endphp

<div
    wire:key="ordering-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
    class="{{ $qzTheme }}w-full space-y-6"
>
    <div class="text-center">
        <h2 data-test="spectator-question-body" class="text-3xl font-bold text-zinc-900 dark:text-white">
            {{ $currentQuestion['body'] ?? '' }}
        </h2>
    </div>

    @if($phase === 'review')
        @php
            $correctOrder = is_array($correctAnswer) ? array_values($correctAnswer) : [];
        @endphp
        <p class="text-center font-semibold text-green-600 dark:text-green-400">
            {{ __('Correct order:') }}
        </p>
        <ol class="mx-auto max-w-xl space-y-2" data-test="ordering-correct-order">
            @foreach($correctOrder as $index => $label)
                <li class="flex items-center gap-3 rounded-xl border-2 border-green-500 bg-green-100 p-4 text-lg font-semibold text-green-900 dark:bg-green-900/40 dark:text-green-200">
                    <span class="text-green-600 dark:text-green-400">{{ $index + 1 }}.</span>
                    <span>{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    @else
        @php
            $displayItems = collect($currentQuestion['options'] ?? [])
                ->map(fn ($option) => $option['label'] ?? $option)
                ->values()
                ->all();
        @endphp
        <ul class="mx-auto max-w-xl space-y-2">
            @foreach($displayItems as $label)
                <li class="rounded-xl border-2 border-zinc-200 bg-white p-4 text-center text-lg font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    {{ $label }}
                </li>
            @endforeach
        </ul>
        <p class="text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Players are arranging the items...') }}
        </p>
    @endif
</div>
