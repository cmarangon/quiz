<div class="mx-auto max-w-4xl p-6">
    <h1 class="mb-6 text-2xl font-bold dark:text-white">
        {{ $this->quiz ? 'Edit Quiz' : 'Create Quiz' }}
    </h1>

    {{-- Quiz Form --}}
    <div class="mb-8 rounded-lg border border-neutral-200 p-6 dark:border-neutral-700">
        <div class="space-y-4">
            <div>
                <flux:input wire:model="title" label="Title" placeholder="Enter quiz title" />
                @error('title') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <flux:textarea wire:model="description" label="Description" placeholder="Describe your quiz" rows="3" />
            </div>

            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm dark:text-neutral-300">
                    <input type="checkbox" wire:model="enableTimeBonus" class="rounded border-neutral-300 dark:border-neutral-600">
                    Enable Time Bonus
                </label>
                <label class="flex items-center gap-2 text-sm dark:text-neutral-300">
                    <input type="checkbox" wire:model="enableStreaks" class="rounded border-neutral-300 dark:border-neutral-600">
                    Enable Streaks
                </label>
            </div>

            <div>
                <flux:button wire:click="save" variant="primary">
                    {{ $this->quiz ? 'Update Quiz' : 'Create Quiz' }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Categories Section (only shown when quiz exists) --}}
    @if($this->quiz)
        <div class="mb-6">
            <h2 class="mb-4 text-xl font-semibold dark:text-white">Categories</h2>

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
                                <li>{{ $question->body }} ({{ $question->points }} pts)</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">No questions yet.</p>
                    @endif
                </div>
            @endforeach

            {{-- Add Category Form --}}
            <div class="mt-4 rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                <h3 class="mb-3 text-sm font-medium dark:text-neutral-300">Add Category</h3>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="newCategoryName" placeholder="Category name" size="sm" />
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
                        Add
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
