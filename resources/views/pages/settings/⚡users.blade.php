<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(15),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                            <th class="py-2 pr-4">{{ __('Name') }}</th>
                            <th class="py-2 pr-4">{{ __('Email') }}</th>
                            <th class="py-2 pr-4">{{ __('Created') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">
                                    {{ $user->name }}
                                    @if ($user->id === Auth::id())
                                        <flux:badge size="sm" class="ms-2">{{ __('You') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2 pr-4">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 text-right"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}
            </div>
        </div>
    </x-pages::settings.layout>
</section>
