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
    $themeBadge = $isThemed ? config('themes.'.$themeKey.'.badge') : null;
@endphp

@if($isThemed)
    <div
        wire:key="match-pairs-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="matchPairs(@js(['left' => $leftItems, 'right' => $rightItems]))"
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

            <p class="qz-hint">{{ __('Tap a card on the left, then its match on the right.') }}</p>

            <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
                <ul class="qz-orderlist" data-test="match-pairs-left">
                    <template x-for="(item, index) in left" :key="'left-' + index">
                        <li
                            x-on:click="tapLeft(index)"
                            data-test="match-pairs-left-item"
                            class="qz-option qz-order"
                            x-bind:class="pairedWith(index) !== null ? colorFor(index) : (selectedLeft === index ? 'qz-option--armed' : '')"
                        >
                            <img x-show="item.kind === 'image'" x-bind:src="item.kind === 'image' ? item.value : null" class="h-16 w-16 rounded object-cover" alt="" />
                            <span x-show="item.kind === 'text'" x-text="item.value" class="qz-order__label"></span>
                        </li>
                    </template>
                </ul>
                <ul class="qz-orderlist" data-test="match-pairs-right">
                    <template x-for="(item, index) in right" :key="'right-' + index">
                        <li
                            x-on:click="tapRight(index)"
                            data-test="match-pairs-right-item"
                            class="qz-option qz-order"
                            x-bind:class="rightUsedAt(index) !== -1 ? colorFor(rightUsedAt(index)) : ''"
                        >
                            <img x-show="item.kind === 'image'" x-bind:src="item.kind === 'image' ? item.value : null" class="h-16 w-16 rounded object-cover" alt="" />
                            <span x-show="item.kind === 'text'" x-text="item.value" class="qz-order__label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <button
                type="button"
                x-on:click="submit()"
                x-bind:disabled="submitted || ! isComplete() || (typeof expired !== 'undefined' && expired)"
                data-test="match-pairs-submit"
                class="qz-cta"
            >
                {{ __('Submit matches') }}
            </button>
        </div>
    </div>
@else
    <div
        wire:key="match-pairs-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="matchPairs(@js(['left' => $leftItems, 'right' => $rightItems]))"
        class="w-full space-y-4"
    >
        @if($currentQuestion['body'] ?? null)
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currentQuestion['body'] }}</h2>
        @endif

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Tap a card on the left, then its match on the right.') }}
        </p>

        <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
            <ul class="space-y-2" data-test="match-pairs-left">
                <template x-for="(item, index) in left" :key="'left-' + index">
                    <li
                        x-on:click="tapLeft(index)"
                        data-test="match-pairs-left-item"
                        class="flex items-center justify-center rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                        x-bind:class="pairedWith(index) !== null ? 'ring-2 ring-blue-500' : (selectedLeft === index ? 'ring-2 ring-amber-400' : '')"
                    >
                        <img x-show="item.kind === 'image'" x-bind:src="item.kind === 'image' ? item.value : null" class="h-16 w-16 rounded object-cover" alt="" />
                        <span x-show="item.kind === 'text'" x-text="item.value" class="font-medium text-zinc-900 dark:text-white"></span>
                    </li>
                </template>
            </ul>
            <ul class="space-y-2" data-test="match-pairs-right">
                <template x-for="(item, index) in right" :key="'right-' + index">
                    <li
                        x-on:click="tapRight(index)"
                        data-test="match-pairs-right-item"
                        class="flex items-center justify-center rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                        x-bind:class="rightUsedAt(index) !== -1 ? 'ring-2 ring-blue-500' : ''"
                    >
                        <img x-show="item.kind === 'image'" x-bind:src="item.kind === 'image' ? item.value : null" class="h-16 w-16 rounded object-cover" alt="" />
                        <span x-show="item.kind === 'text'" x-text="item.value" class="font-medium text-zinc-900 dark:text-white"></span>
                    </li>
                </template>
            </ul>
        </div>

        <button
            type="button"
            x-on:click="submit()"
            x-bind:disabled="submitted || ! isComplete() || (typeof expired !== 'undefined' && expired)"
            data-test="match-pairs-submit"
            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-lg font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Submit matches') }}
        </button>
    </div>
@endif
