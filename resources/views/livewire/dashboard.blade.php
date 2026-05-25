<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    {{-- My Quizzes --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('My Quizzes') }}</h2>
            <a href="{{ route('quizzes.create') }}" class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">+ {{ __('New Quiz') }}</a>
        </div>

        @if($quizzes->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __('No quizzes yet.') }} <a href="{{ route('quizzes.create') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Create your first quiz') }}</a>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Title') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Categories') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($quizzes as $quiz)
                            <tr wire:key="quiz-{{ $quiz->id }}">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $quiz->title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $quiz->categories_count }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a href="{{ route('quizzes.edit', $quiz) }}" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Edit') }}</a>
                                    <form action="{{ route('game.create', $quiz) }}" method="POST" class="ml-3 inline">
                                        @csrf
                                        <button type="submit" class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-500">{{ __('Play') }}</button>
                                    </form>
                                    <flux:modal.trigger name="confirm-action">
                                        <flux:button size="sm" variant="danger" class="ml-3"
                                                     wire:click="confirmDeleteQuiz({{ $quiz->id }})"
                                                     data-test="delete-quiz-{{ $quiz->id }}">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-action-message class="mt-2" on="quiz-deleted">{{ __('Quiz deleted.') }}</x-action-message>
    </div>

    {{-- Hosted Games --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Hosted Games') }}</h2>

        @if($hostedSessions->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __('No hosted games yet.') }}
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Players') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($hostedSessions as $session)
                            <tr wire:key="session-{{ $session->id }}">
                                <td class="px-4 py-3">
                                    @if($session->status === 'finished')
                                        <flux:checkbox wire:model.live="selectedSessionIds" value="{{ $session->id }}"
                                                       data-test="select-session-{{ $session->id }}" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $session->quiz->title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->players_count }} {{ __('players') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        @if($session->status === 'finished') bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300
                                        @elseif($session->status === 'playing') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                                        @else bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300
                                        @endif">
                                        {{ ucfirst($session->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if($session->status !== 'finished')
                                        <flux:modal.trigger name="confirm-action">
                                            <flux:button size="sm" variant="danger"
                                                         wire:click="confirmEndSession({{ $session->id }})"
                                                         data-test="end-session-{{ $session->id }}">
                                                {{ __('End') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($selectedSessionIds) > 0)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-neutral-800">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __(':count selected', ['count' => count($selectedSessionIds)]) }}
                    </span>
                    <flux:modal.trigger name="confirm-action">
                        <flux:button size="sm" variant="danger"
                                     wire:click="confirmClearSessions"
                                     data-test="clear-sessions">
                            {{ __('Delete selected') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        @endif

        <x-action-message class="mt-2" on="game-ended">{{ __('Game ended.') }}</x-action-message>
        <x-action-message class="mt-2" on="history-cleared">{{ __('History cleared.') }}</x-action-message>
    </div>

    {{-- My Game History --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('My Game History') }}</h2>

        @if($playerEntries->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __("You haven't played any games yet.") }}
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Score') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($playerEntries as $entry)
                            <tr wire:key="player-{{ $entry->id }}">
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selectedPlayerIds" value="{{ $entry->id }}"
                                                   data-test="select-player-{{ $entry->id }}" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $entry->gameSession->quiz->title }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $entry->score }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $entry->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($selectedPlayerIds) > 0)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-neutral-800">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __(':count selected', ['count' => count($selectedPlayerIds)]) }}
                    </span>
                    <flux:modal.trigger name="confirm-action">
                        <flux:button size="sm" variant="danger"
                                     wire:click="confirmClearPlayerEntries"
                                     data-test="clear-players">
                            {{ __('Delete selected') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        @endif
    </div>

    {{-- Shared confirmation modal --}}
    <flux:modal name="confirm-action" focusable class="max-w-lg" data-test="confirm-action">
        <form wire:submit="runPendingAction" class="space-y-6">
            <div>
                @if($pendingAction === 'delete-quiz')
                    <flux:heading size="lg">{{ __('Delete this quiz?') }}</flux:heading>
                    <flux:subheading>{{ __('All categories, questions and past game sessions will be removed.') }}</flux:subheading>
                @elseif($pendingAction === 'end-session')
                    <flux:heading size="lg">{{ __('End this game?') }}</flux:heading>
                    <flux:subheading>{{ __('Its status will be set to finished.') }}</flux:subheading>
                @elseif($pendingAction === 'clear-sessions')
                    <flux:heading size="lg">{{ __('Delete :count hosted games?', ['count' => count($selectedSessionIds)]) }}</flux:heading>
                @elseif($pendingAction === 'clear-players')
                    <flux:heading size="lg">{{ __('Delete :count entries from your game history?', ['count' => count($selectedPlayerIds)]) }}</flux:heading>
                @endif
            </div>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="confirm-pending-action">
                    @if($pendingAction === 'end-session')
                        {{ __('End') }}
                    @else
                        {{ __('Delete') }}
                    @endif
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
