<div class="mx-auto max-w-4xl p-6">
    <h1 class="mb-6 text-2xl font-bold dark:text-white">
        {{ $this->quiz ? __('Edit Quiz') : __('Create Quiz') }}
    </h1>

    {{-- Quiz Form --}}
    <div class="mb-8 rounded-lg border border-neutral-200 p-6 dark:border-neutral-700">
        <div class="space-y-4">
            <div>
                <flux:input wire:model="title" :label="__('Title')" :placeholder="__('Enter quiz title')" />
                @error('title') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <flux:textarea wire:model="description" :label="__('Description')" :placeholder="__('Describe your quiz')" rows="3" />
            </div>

            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm dark:text-neutral-300">
                    <input type="checkbox" wire:model="enableTimeBonus" class="rounded border-neutral-300 dark:border-neutral-600">
                    {{ __('Enable Time Bonus') }}
                </label>
                <label class="flex items-center gap-2 text-sm dark:text-neutral-300">
                    <input type="checkbox" wire:model="enableStreaks" class="rounded border-neutral-300 dark:border-neutral-600">
                    {{ __('Enable Streaks') }}
                </label>
                <label class="flex items-center gap-2 text-sm dark:text-neutral-300">
                    <input type="checkbox" wire:model="showScoreboard" class="rounded border-neutral-300 dark:border-neutral-600">
                    {{ __('Show Scoreboard') }}
                </label>
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Presentation style') }}</span>
                @php
                $styleConfigs = [
                    'party-pop' => [
                        'label'   => 'Party Pop',
                        'swatch'  => 'linear-gradient(160deg, #3b0764, #1e1b4b 55%, #0f172a)',
                        'accent'  => '#f472b6',
                        'blobs'   => [
                            ['background' => 'radial-gradient(circle at 30% 30%, #f472b6, #db2777)', 'style' => 'width:52px;height:52px;top:-14px;right:-14px;opacity:.55;'],
                            ['background' => 'radial-gradient(circle at 30% 30%, #5eead4, #0d9488)',  'style' => 'width:36px;height:36px;bottom:18px;left:-10px;opacity:.45;'],
                        ],
                    ],
                    'game-show' => [
                        'label'   => 'Game Show',
                        'swatch'  => 'radial-gradient(ellipse at top, #1f2937, #0b0f1a 70%)',
                        'accent'  => '#fbbf24',
                        'blobs'   => [
                            ['background' => 'radial-gradient(circle, rgba(251,191,36,.4), transparent 60%)', 'style' => 'width:100px;height:80px;top:-30px;left:50%;transform:translateX(-50%);'],
                        ],
                    ],
                    'bright-bouncy' => [
                        'label'   => 'Bright & Bouncy',
                        'swatch'  => 'linear-gradient(165deg, #0f766e, #0e7490 50%, #1e3a8a)',
                        'accent'  => '#5eead4',
                        'blobs'   => [
                            ['background' => 'rgba(255,255,255,.12)', 'style' => 'width:32px;height:32px;top:-8px;left:-8px;'],
                            ['background' => 'rgba(255,255,255,.10)', 'style' => 'width:20px;height:20px;bottom:22px;right:6px;'],
                        ],
                    ],
                ];
                @endphp
                <div class="flex flex-wrap gap-3" data-test="presentation-style-picker">
                    @foreach($styleConfigs as $key => $cfg)
                        @php $selected = $presentationStyle === $key; @endphp
                        <button type="button"
                            wire:click="$set('presentationStyle', '{{ $key }}')"
                            data-test="presentation-style-option"
                            data-style="{{ $key }}"
                            style="{{ $selected ? 'outline: 2.5px solid '.$cfg['accent'].'; outline-offset: 2px;' : '' }}"
                            @class([
                                'flex flex-col overflow-hidden rounded-xl border-2 text-left transition focus:outline-none',
                                'shadow-md' => $selected,
                                'border-transparent opacity-70 hover:opacity-90' => !$selected,
                            ])
                        >
                            {{-- gradient swatch --}}
                            <div class="relative h-14 w-32 overflow-hidden"
                                 style="background: {{ $cfg['swatch'] }};">
                                @foreach($cfg['blobs'] as $blob)
                                    <div class="absolute rounded-full"
                                         style="background: {{ $blob['background'] }}; {{ $blob['style'] }}"></div>
                                @endforeach
                                {{-- accent stripe at bottom of swatch --}}
                                <div class="absolute bottom-0 left-0 right-0 h-1"
                                     style="background: {{ $cfg['accent'] }}; opacity: .7;"></div>
                            </div>
                            {{-- label --}}
                            <div @class([
                                    'px-3 py-1.5 text-xs font-semibold',
                                    'bg-white text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100' => !$selected,
                                    'text-neutral-800 dark:text-neutral-100' => $selected,
                                ])
                                 style="{{ $selected ? 'background: '.$cfg['accent'].'22;' : '' }}"
                            >
                                {{ __($cfg['label']) }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:input wire:model="defaultQuestionDuration" :label="__('Default question duration (seconds)')" type="number" min="5" size="sm" />
                @error('defaultQuestionDuration') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <flux:button wire:click="save" variant="primary">
                    {{ $this->quiz ? __('Update Quiz') : __('Create Quiz') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Categories Section (only shown when quiz exists) --}}
    @if($this->quiz)
        <div class="mb-6">
            <h2 class="mb-4 text-xl font-semibold dark:text-white">{{ __('Categories') }}</h2>

            @foreach($categories as $category)
                <div class="mb-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-medium dark:text-white">{{ $category->name }}</h3>
                        <span class="rounded-full bg-neutral-100 px-2 py-1 text-xs dark:bg-neutral-700 dark:text-neutral-300">
                            {{ $category->theme }}
                        </span>
                    </div>

                    @if($category->questions->isNotEmpty())
                        <ul class="ml-4 space-y-1 text-sm text-neutral-600 dark:text-neutral-400">
                            @foreach($category->questions as $question)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="flex items-start gap-2">
                                        <span class="mt-1 text-neutral-400">&bull;</span>
                                        <span class="flex flex-col">
                                            <span>{{ $question->body }} ({{ $question->points }} {{ __('pts') }}, {{ $question->effectiveTimeLimitSeconds() }}s{{ $question->time_limit_seconds === null ? ', '.__('Default') : '' }})</span>
                                            @if($question->comment)
                                                <span class="text-xs italic text-neutral-400 dark:text-neutral-500">{{ $question->comment }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <flux:button wire:click="editQuestion({{ $question->id }})" size="xs" variant="ghost">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button wire:click="deleteQuestion({{ $question->id }})" wire:confirm="{{ __('Are you sure you want to delete this question?') }}" size="xs" variant="ghost" class="text-red-500">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('No questions yet.') }}</p>
                    @endif

                    @php($isEditingInCategory = $editingQuestionId && $category->questions->contains('id', $editingQuestionId))

                    {{-- Add / Edit Question Form --}}
                    @if($addingQuestionToCategoryId === $category->id || $isEditingInCategory)
                        <div class="mt-3 rounded-md border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                            <h4 class="mb-3 text-sm font-medium dark:text-neutral-300">{{ $editingQuestionId ? __('Edit Question') : __('Add Question') }}</h4>
                            <div class="space-y-3">
                                <div>
                                    <flux:textarea wire:model="questionBody" :label="__('Question')" :placeholder="__('Enter your question')" rows="2" />
                                    @error('questionBody') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <flux:textarea wire:model="questionComment" :label="__('Admin note (private)')" :placeholder="__('Optional note visible only to you')" rows="2" />
                                </div>

                                <div class="flex gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Type') }}</label>
                                        <select wire:model.live="questionType" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                                            <option value="multiple_choice">{{ __('Multiple Choice') }}</option>
                                            <option value="true_false">{{ __('True / False') }}</option>
                                            <option value="ordering">{{ __('Ordering') }}</option>
                                            <option value="geo_guesser">{{ __('Geo Guesser') }}</option>
                                            <option value="match_pairs">{{ __('Match Pairs') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <flux:input wire:model="questionPoints" :label="__('Points')" type="number" min="1" size="sm" />
                                    </div>
                                    <div>
                                        <flux:input wire:model="questionTimeLimit" :label="__('Time (seconds)')" type="number" min="5" size="sm" :placeholder="__('Default')" />
                                        @error('questionTimeLimit') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Leave blank to use the quiz default.') }}</p>

                                @if($questionType === 'multiple_choice' || $questionType === 'ordering')
                                    <div>
                                        <label class="mb-1 block text-sm font-medium dark:text-neutral-300">
                                            {{ $questionType === 'ordering' ? __('Items (enter in the correct order)') : __('Options') }}
                                        </label>
                                        <div class="space-y-2">
                                            @foreach($questionOptions as $index => $option)
                                                <div class="flex gap-2">
                                                    <flux:input wire:model.live.debounce.300ms="questionOptions.{{ $index }}" :placeholder="($questionType === 'ordering' ? __('Item') : __('Option')) . ' ' . ($index + 1)" size="sm" class="flex-1" />
                                                    @if(count($questionOptions) > 2)
                                                        <flux:button wire:click="removeQuestionOption({{ $index }})" size="sm" variant="ghost" class="text-red-500">
                                                            &times;
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        @error('questionOptions.*') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                        <flux:button wire:click="addQuestionOption" size="sm" variant="ghost" class="mt-1">
                                            + {{ $questionType === 'ordering' ? __('Add Item') : __('Add Option') }}
                                        </flux:button>
                                    </div>
                                @endif

                                @if($questionType === 'multiple_choice' || $questionType === 'true_false')
                                <div>
                                    <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Correct Answer') }}</label>
                                    @if($questionType === 'true_false')
                                        <select wire:model="questionCorrectAnswer" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                                            <option value="">{{ __('Select...') }}</option>
                                            <option value="True">{{ __('True') }}</option>
                                            <option value="False">{{ __('False') }}</option>
                                        </select>
                                    @else
                                        <select wire:model="questionCorrectAnswer" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                                            <option value="">{{ __('Select correct answer...') }}</option>
                                            @foreach($questionOptions as $index => $option)
                                                @if($option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif
                                    @error('questionCorrectAnswer') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                @if($questionType === 'geo_guesser')
                                <div
                                    wire:key="geo-picker-{{ $editingQuestionId ?? 'new' }}"
                                    x-data="geoPicker({ latField: 'questionGeoLat', lngField: 'questionGeoLng', thresholdField: 'questionGeoThresholdKm', maxDistField: 'questionGeoMaxDistanceKm', center: { lat: 20, lng: 0 }, zoom: 2 })"
                                >
                                    <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Correct Location') }}</label>
                                    <p class="mb-2 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Click the map to drop a pin (drag to fine-tune). The coordinates fill in automatically.') }}</p>
                                    <div wire:ignore>
                                        <div
                                            x-ref="map"
                                            data-test="geo-picker-map"
                                            class="h-64 w-full overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700"
                                        ></div>
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <flux:input wire:model="questionGeoLat" :label="__('Latitude')" type="number" step="any" min="-90" max="90" size="sm" />
                                        <flux:input wire:model="questionGeoLng" :label="__('Longitude')" type="number" step="any" min="-180" max="180" size="sm" />
                                    </div>
                                    @error('questionGeoLat') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                    @error('questionGeoLng') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                                    <div class="mt-3 flex gap-2">
                                        <flux:input wire:model="questionGeoThresholdKm" :label="__('Full-points radius (km)')" type="number" step="any" min="0" size="sm" :placeholder="__('Default')" />
                                        <flux:input wire:model="questionGeoMaxDistanceKm" :label="__('Zero-points distance (km)')" type="number" step="any" min="0" size="sm" :placeholder="__('Default')" />
                                    </div>
                                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Leave blank to use the quiz defaults. Guesses within the radius earn full points; points decay to zero at the max distance.') }}</p>
                                    @error('questionGeoThresholdKm') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                    @error('questionGeoMaxDistanceKm') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                @if($questionType === 'match_pairs')
                                <div data-test="match-pairs-form" class="space-y-2">
                                    <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Pairs (left matches right)') }}</label>
                                    @foreach($questionPairs as $index => $pair)
                                        <div class="grid grid-cols-2 gap-2" wire:key="match-pair-{{ $index }}">
                                            @foreach(['left', 'right'] as $side)
                                                <div class="space-y-1 rounded-md border border-neutral-200 p-2 dark:border-neutral-700">
                                                    <div class="flex gap-1">
                                                        <flux:button type="button" size="xs" :variant="$pair[$side]['kind'] === 'text' ? 'primary' : 'ghost'"
                                                                      wire:click="$set('questionPairs.{{ $index }}.{{ $side }}.kind', 'text')"
                                                                      data-test="match-pair-kind-text-{{ $index }}-{{ $side }}">
                                                            {{ __('Text') }}
                                                        </flux:button>
                                                        <flux:button type="button" size="xs" :variant="$pair[$side]['kind'] === 'image' ? 'primary' : 'ghost'"
                                                                      wire:click="$set('questionPairs.{{ $index }}.{{ $side }}.kind', 'image')"
                                                                      data-test="match-pair-kind-image-{{ $index }}-{{ $side }}">
                                                            {{ __('Image') }}
                                                        </flux:button>
                                                    </div>

                                                    @if($pair[$side]['kind'] === 'image')
                                                        @if($pair[$side]['existingImage'] && ! $pair[$side]['image'])
                                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pair[$side]['existingImage']) }}"
                                                                 class="h-12 w-12 rounded object-cover" alt="" />
                                                        @endif
                                                        <input type="file"
                                                               wire:model="questionPairs.{{ $index }}.{{ $side }}.image"
                                                               data-test="match-pair-image-{{ $index }}-{{ $side }}"
                                                               class="block text-xs" />
                                                        @error("questionPairs.$index.$side.image") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                                    @else
                                                        <flux:input wire:model.live.debounce.300ms="questionPairs.{{ $index }}.{{ $side }}.text"
                                                                    :placeholder="$side === 'left' ? __('Left :n', ['n' => $index + 1]) : __('Right :n', ['n' => $index + 1])"
                                                                    size="sm" />
                                                        @error("questionPairs.$index.$side.text") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    @error('questionPairs') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                <div class="flex gap-2">
                                    <flux:button wire:click="saveQuestion" size="sm" variant="primary">
                                        {{ $editingQuestionId ? __('Update Question') : __('Save Question') }}
                                    </flux:button>
                                    <flux:button wire:click="cancelAddQuestion" size="sm" variant="ghost">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-3">
                            <flux:button wire:click="showAddQuestion({{ $category->id }})" size="sm" variant="ghost">
                                + {{ __('Add Question') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Add Category Form --}}
            <div class="mt-4 rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                <h3 class="mb-3 text-sm font-medium dark:text-neutral-300">{{ __('Add Category') }}</h3>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="newCategoryName" :placeholder="__('Category name')" size="sm" />
                        @error('newCategoryName') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <select wire:model="newCategoryTheme" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                            <option value="" disabled>{{ __('Select a theme') }}</option>
                            @foreach($themeKeys as $theme)
                                <option value="{{ $theme }}">{{ ucfirst(str_replace('-', ' ', $theme)) }}</option>
                            @endforeach
                        </select>
                        @error('newCategoryTheme') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <flux:button wire:click="addCategory" size="sm" variant="primary">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
