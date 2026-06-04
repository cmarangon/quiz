@php
    $orderingItems = collect($currentQuestion['options'] ?? [])
        ->map(fn ($option) => $option['label'] ?? $option)
        ->values()
        ->all();
    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;
    $themeBadge = $isThemed ? config('themes.'.$themeKey.'.badge') : null;
@endphp

@if($isThemed)
    <div
        wire:key="ordering-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="orderingList(@js(['items' => $orderingItems]))"
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

            <p class="qz-hint">{{ __('Drag the items into the correct order.') }}</p>

            <ul class="qz-orderlist" data-test="ordering-list">
                <template x-for="(item, index) in items" :key="item">
                    <li
                        draggable="true"
                        x-on:dragstart="onDragStart(index)"
                        x-on:dragover.prevent
                        x-on:drop.prevent="onDrop(index)"
                        data-test="ordering-item"
                        x-bind:data-answer-label="item"
                        class="qz-option qz-order"
                        x-bind:class="colorFor(item)"
                    >
                        <span class="qz-key" x-text="index + 1"></span>
                        <span class="qz-order__label" x-text="item"></span>
                        <span class="qz-moves">
                            <button
                                type="button"
                                x-on:click="moveUp(index)"
                                x-bind:disabled="index === 0"
                                data-test="ordering-move-up"
                                class="qz-move"
                                aria-label="{{ __('Move up') }}"
                            >&uarr;</button>
                            <button
                                type="button"
                                x-on:click="moveDown(index)"
                                x-bind:disabled="index === items.length - 1"
                                data-test="ordering-move-down"
                                class="qz-move"
                                aria-label="{{ __('Move down') }}"
                            >&darr;</button>
                        </span>
                    </li>
                </template>
            </ul>

            <button
                type="button"
                x-on:click="submit()"
                x-bind:disabled="submitted || (typeof expired !== 'undefined' && expired)"
                data-test="ordering-submit"
                class="qz-cta"
            >
                {{ __('Submit order') }}
            </button>
        </div>
    </div>
@else
    <div
        wire:key="ordering-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="orderingList(@js(['items' => $orderingItems]))"
        class="w-full space-y-4"
    >
        @if($currentQuestion['body'] ?? null)
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currentQuestion['body'] }}</h2>
        @endif

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Drag the items into the correct order.') }}
        </p>

        <ul class="space-y-2" data-test="ordering-list">
            <template x-for="(item, index) in items" :key="item">
                <li
                    draggable="true"
                    x-on:dragstart="onDragStart(index)"
                    x-on:dragover.prevent
                    x-on:drop.prevent="onDrop(index)"
                    data-test="ordering-item"
                    x-bind:data-answer-label="item"
                    class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-left shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                    <span class="flex items-center gap-3">
                        <span class="text-zinc-400" x-text="index + 1"></span>
                        <span class="font-medium text-zinc-900 dark:text-white" x-text="item"></span>
                    </span>
                    <span class="flex items-center gap-1">
                        <button
                            type="button"
                            x-on:click="moveUp(index)"
                            x-bind:disabled="index === 0"
                            data-test="ordering-move-up"
                            class="rounded-lg px-2 py-1 text-zinc-500 transition hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-700"
                            aria-label="{{ __('Move up') }}"
                        >&uarr;</button>
                        <button
                            type="button"
                            x-on:click="moveDown(index)"
                            x-bind:disabled="index === items.length - 1"
                            data-test="ordering-move-down"
                            class="rounded-lg px-2 py-1 text-zinc-500 transition hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-700"
                            aria-label="{{ __('Move down') }}"
                        >&darr;</button>
                    </span>
                </li>
            </template>
        </ul>

        <button
            type="button"
            x-on:click="submit()"
            x-bind:disabled="submitted || (typeof expired !== 'undefined' && expired)"
            data-test="ordering-submit"
            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-lg font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Submit order') }}
        </button>
    </div>
@endif
