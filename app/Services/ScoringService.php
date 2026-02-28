<?php

namespace App\Services;

use App\Models\Question;

class ScoringService
{
    public function calculate(Question $question, int $timeTakenMs, int $streak, array $settings): int
    {
        $base = $question->points;

        $timeFactor = 1.0;
        if ($settings['enable_time_bonus'] ?? true) {
            $limitMs = $question->time_limit_seconds * 1000;
            $remaining = max(0, $limitMs - $timeTakenMs);
            $timeFactor = $remaining / $limitMs;
        }

        $streakMultiplier = 1.0;
        if ($settings['enable_streaks'] ?? true) {
            $streakMultiplier = match (true) {
                $streak >= 5 => 2.0,
                $streak >= 3 => 1.5,
                default => 1.0,
            };
        }

        return (int) round($base * $timeFactor * $streakMultiplier);
    }
}
