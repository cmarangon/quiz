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

    public ?int $userIdToDelete = null;

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

    public function confirmDelete(int $userId): void
    {
        $this->userIdToDelete = $userId;
    }

    public function deleteUser(): void
    {
        abort_if($this->userIdToDelete === null, 400);
        abort_if($this->userIdToDelete === Auth::id(), 403);

        User::findOrFail($this->userIdToDelete)->delete();

        $this->userIdToDelete = null;

        $this->dispatch('user-deleted');
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
                                    @if ($user->id !== Auth::id())
                                        <flux:modal.trigger name="confirm-user-delete">
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="confirmDelete({{ $user->id }})"
                                                data-test="delete-user-{{ $user->id }}"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}

                <x-action-message class="mt-2" on="user-deleted">
                    {{ __('User deleted.') }}
                </x-action-message>
            </div>
        </div>

        <flux:modal name="confirm-user-delete" focusable class="max-w-lg">
            <form wire:submit="deleteUser" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete this user?') }}</flux:heading>

                    <flux:subheading>
                        {{ __('Deleting this user also removes all of their quizzes and hosted games. This cannot be undone.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                        {{ __('Delete user') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    </x-pages::settings.layout>
</section>
