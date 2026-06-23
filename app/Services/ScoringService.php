<?php

namespace App\Services;

use App\Models\Question;

class ScoringService
{
    public function calculate(Question $question, int $timeTakenMs, int $streak, array $settings, float $accuracyFactor = 1.0): int
    {
        return $this->breakdown($question, $timeTakenMs, $streak, $settings, $accuracyFactor)['total'];
    }

    /**
     * Same scoring as {@see calculate()} but also returns the individual factors
     * and the running point subtotals after each one, so the player UI can
     * explain exactly how the final score was reached.
     *
     * @return array{
     *     base: int,
     *     accuracy: float,
     *     time_factor: float,
     *     streak_multiplier: float,
     *     time_bonus_enabled: bool,
     *     streak_enabled: bool,
     *     correct_points: int,
     *     speed_points: int,
     *     total: int,
     * }
     */
    public function breakdown(Question $question, int $timeTakenMs, int $streak, array $settings, float $accuracyFactor = 1.0): array
    {
        $base = $question->points;

        $accuracyFactor = max(0.0, min(1.0, $accuracyFactor));

        $timeBonusEnabled = (bool) ($settings['enable_time_bonus'] ?? true);
        $timeFactor = 1.0;
        if ($timeBonusEnabled) {
            $limitMs = $question->effectiveTimeLimitSeconds() * 1000;
            $remaining = max(0, $limitMs - $timeTakenMs);
            $timeFactor = $limitMs > 0 ? $remaining / $limitMs : 0.0;
        }

        $streakEnabled = (bool) ($settings['enable_streaks'] ?? true);
        $streakMultiplier = 1.0;
        if ($streakEnabled) {
            $streakMultiplier = match (true) {
                $streak >= 5 => 2.0,
                $streak >= 3 => 1.5,
                default => 1.0,
            };
        }

        return [
            'base' => $base,
            'accuracy' => $accuracyFactor,
            'time_factor' => $timeFactor,
            'streak_multiplier' => $streakMultiplier,
            'time_bonus_enabled' => $timeBonusEnabled,
            'streak_enabled' => $streakEnabled,
            // Running point subtotals after applying each factor in turn.
            'correct_points' => (int) round($base * $accuracyFactor),
            'speed_points' => (int) round($base * $accuracyFactor * $timeFactor),
            'total' => (int) round($base * $accuracyFactor * $timeFactor * $streakMultiplier),
        ];
    }
}
