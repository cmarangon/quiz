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
                <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
                     wire:key="quiz-{{ $quiz->id }}">
                    <div>
                        <h2 class="text-lg font-semibold dark:text-white">{{ $quiz->title }}</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $quiz->categories_count }} {{ trans_choice('{1} category|[2,*] categories', $quiz->categories_count) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('quizzes.edit', $quiz) }}" wire:navigate>
                            <flux:button variant="ghost" icon="pencil" size="sm">
                                {{ __('Edit') }}
                            </flux:button>
                        </a>
                        <flux:modal.trigger name="confirm-action">
                            <flux:button variant="danger" size="sm"
                                         wire:click="confirmDeleteQuiz({{ $quiz->id }})"
                                         data-test="delete-quiz-{{ $quiz->id }}">
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-action-message class="mt-4" on="quiz-deleted">{{ __('Quiz deleted.') }}</x-action-message>

    <flux:modal name="confirm-action" focusable class="max-w-lg" data-test="confirm-action">
        <form wire:submit="runPendingAction" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this quiz?') }}</flux:heading>
                <flux:subheading>{{ __('All categories, questions and past game sessions will be removed.') }}</flux:subheading>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="confirm-pending-action">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
