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
                        <ul class="ml-4 list-disc space-y-1 text-sm text-neutral-600 dark:text-neutral-400">
                            @foreach($category->questions as $question)
                                <li>{{ $question->body }} ({{ $question->points }} {{ __('pts') }}, {{ $question->time_limit_seconds }}s)</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('No questions yet.') }}</p>
                    @endif

                    {{-- Add Question Form --}}
                    @if($addingQuestionToCategoryId === $category->id)
                        <div class="mt-3 rounded-md border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                            <h4 class="mb-3 text-sm font-medium dark:text-neutral-300">{{ __('Add Question') }}</h4>
                            <div class="space-y-3">
                                <div>
                                    <flux:textarea wire:model="questionBody" :label="__('Question')" :placeholder="__('Enter your question')" rows="2" />
                                    @error('questionBody') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div class="flex gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Type') }}</label>
                                        <select wire:model.live="questionType" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                                            <option value="multiple_choice">{{ __('Multiple Choice') }}</option>
                                            <option value="true_false">{{ __('True / False') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <flux:input wire:model="questionPoints" :label="__('Points')" type="number" min="1" size="sm" />
                                    </div>
                                    <div>
                                        <flux:input wire:model="questionTimeLimit" :label="__('Time (seconds)')" type="number" min="5" size="sm" />
                                    </div>
                                </div>

                                @if($questionType === 'multiple_choice')
                                    <div>
                                        <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Options') }}</label>
                                        <div class="space-y-2">
                                            @foreach($questionOptions as $index => $option)
                                                <div class="flex gap-2">
                                                    <flux:input wire:model.live.debounce.300ms="questionOptions.{{ $index }}" :placeholder="__('Option') . ' ' . ($index + 1)" size="sm" class="flex-1" />
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
                                            + {{ __('Add Option') }}
                                        </flux:button>
                                    </div>
                                @endif

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

                                <div class="flex gap-2">
                                    <flux:button wire:click="saveQuestion" size="sm" variant="primary">
                                        {{ __('Save Question') }}
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
                            @foreach($themeKeys as $theme)
                                <option value="{{ $theme }}">{{ ucfirst($theme) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <flux:button wire:click="addCategory" size="sm" variant="primary">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
