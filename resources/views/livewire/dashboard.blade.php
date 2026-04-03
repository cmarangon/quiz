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
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $quiz->title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $quiz->categories_count }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a href="{{ route('quizzes.edit', $quiz) }}" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Edit') }}</a>
                                    <form action="{{ route('game.create', $quiz) }}" method="POST" class="ml-3 inline">
                                        @csrf
                                        <button type="submit" class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-500">{{ __('Play') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Players') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($hostedSessions as $session)
                            <tr>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Score') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($playerEntries as $entry)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $entry->gameSession->quiz->title }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $entry->score }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $entry->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
