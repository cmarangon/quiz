<div class="mx-auto max-w-4xl p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold dark:text-white">{{ __('My Quizzes') }}</h1>
        <a href="{{ route('quizzes.create') }}" wire:navigate>
            <flux:button variant="primary" icon="plus">
                {{ __('Create Quiz') }}
            </flux:button>
        </a>
    </div>

    @if($quizzes->isEmpty())
        <div class="rounded-lg border border-neutral-200 p-8 text-center dark:border-neutral-700">
            <p class="text-neutral-500 dark:text-neutral-400">{{ __('You have no quizzes yet. Create your first one!') }}</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($quizzes as $quiz)
                <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div>
                        <h2 class="text-lg font-semibold dark:text-white">{{ $quiz->title }}</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $quiz->categories_count }} {{ trans_choice('{1} category|[2,*] categories', $quiz->categories_count) }}
                        </p>
                    </div>
                    <a href="{{ route('quizzes.edit', $quiz) }}" wire:navigate>
                        <flux:button variant="ghost" icon="pencil" size="sm">
                            {{ __('Edit') }}
                        </flux:button>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
