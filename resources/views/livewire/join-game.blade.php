<div class="flex flex-col items-center gap-6">
    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Join Game') }}</h1>
    <p class="text-zinc-500 dark:text-zinc-400">{{ __('Code') }}: <span class="font-mono font-bold text-lg">{{ $code }}</span></p>

    <form wire:submit="join" class="w-full flex flex-col gap-4">
        <flux:input wire:model="nickname" :label="__('Your Nickname')" :placeholder="__('Enter a nickname')" required />

        @error('nickname')
            <p class="text-sm text-red-500">{{ $message }}</p>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Join Game') }}
        </flux:button>
    </form>
</div>
