@php($style = $session?->presentationStyle() ?? 'party-pop')
<div class="qz-stage qz-stage--{{ $style }} qz-stage--has-bg">
    <div class="qz-card">
        @if($style === 'party-pop')
            <span class="qz-blob b1"></span><span class="qz-blob b2"></span>
        @elseif($style === 'game-show')
            <span class="qz-blob spot"></span>
        @else
            <span class="qz-blob u1"></span><span class="qz-blob u2"></span>
        @endif

        <span class="qz-code-pill">{{ __('Code') }} · <span class="qz-codeval">{{ $code }}</span></span>
        <h1 class="qz-title">{{ __('Join Game') }}</h1>

        <form wire:submit="join" class="flex w-full flex-col gap-4">
            <div class="qz-emoji-grid" data-test="join-emoji-grid">
                @foreach(\App\Support\PlayerEmojis::all() as $option)
                    <button type="button"
                        wire:click="$set('emoji', '{{ $option }}')"
                        data-test="join-emoji-option"
                        data-emoji="{{ $option }}"
                        @class(['qz-emoji-btn', 'is-selected' => $emoji === $option])>
                        {{ $option }}
                    </button>
                @endforeach
                <button type="button"
                    wire:click="surpriseMe"
                    data-test="join-emoji-random"
                    title="{{ __('Feeling lucky?') }}"
                    @class(['qz-emoji-btn', 'is-selected' => in_array($emoji, \App\Support\PlayerEmojis::lewd(), true)])>
                    🎲
                </button>
            </div>

            @error('emoji')
                <p class="qz-error">{{ $message }}</p>
            @enderror

            <input type="text"
                class="qz-input"
                wire:model="nickname"
                data-test="join-nickname-input"
                placeholder="{{ __('Enter a nickname') }}"
                required>

            @if($emoji)
                <p class="text-center text-sm opacity-80" data-test="join-name-preview">{{ $emoji }} {{ $nickname ?: __('Your Nickname') }}</p>
            @endif

            @error('nickname')
                <p class="qz-error">{{ $message }}</p>
            @enderror

            <button type="submit" class="qz-btn" data-test="join-submit" @disabled($emoji === '')>
                {{ __('Join Game') }}
            </button>
        </form>
    </div>
</div>
