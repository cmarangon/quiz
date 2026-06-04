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

    /**
     * Cheeky, suggestive emojis hidden behind the "random" button.
     *
     * @return array<int, string>
     */
    public static function lewd(): array
    {
        return [
            '🍆', '🍑', '💦', '🫦',
            '🍌', '💋', '🥒', '🌭',
            '👙', '🩲', '😏', '🤤',
        ];
    }

    /**
     * Every emoji that is valid for a player to use, whether picked from the
     * grid or rolled via the "random" button.
     *
     * @return array<int, string>
     */
    public static function selectable(): array
    {
        return array_values(array_unique(array_merge(self::all(), self::lewd())));
    }

    /**
     * Pick a "random" emoji from the curated lewd list.
     */
    public static function randomLewd(): string
    {
        $lewd = self::lewd();

        return $lewd[array_rand($lewd)];
    }
}
