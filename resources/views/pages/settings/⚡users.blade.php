<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function createUser(CreatesNewUsers $creator): void
    {
        $creator->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset(['name', 'email', 'password', 'password_confirmation']);

        $this->dispatch('user-created');
    }

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
        <div class="my-6 w-full space-y-8">
            <form wire:submit="createUser" class="space-y-4">
                <flux:heading size="sm">{{ __('Add a user') }}</flux:heading>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
                <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="create-user-button">
                        {{ __('Create user') }}
                    </flux:button>

                    <x-action-message class="me-3" on="user-created">
                        {{ __('User created.') }}
                    </x-action-message>
                </div>
            </form>

            <flux:separator variant="subtle" />

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
                                <td class="py-2 text-right">
                                    {{-- Delete button lands in Task 6 --}}
                                </td>
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
