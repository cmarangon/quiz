<?php

namespace App\Support;

class PlayerEmojis
{
    /**
     * Curated set of fun emojis players can pick when joining.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            '🦄', '🐙', '🚀', '🦖',
            '🍕', '👽', '🤖', '🦩',
            '🐸', '🎩', '🔥', '💩',
            '🌮', '👾',
        ];
    }
}
