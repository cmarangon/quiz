<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class GeoGuesserType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.geo-guesser-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.geo-guesser-player';
    }

    /**
     * A guess is "correct" (counts toward streaks) when it lands within the
     * perfect-score threshold radius. Partial credit beyond the threshold is
     * handled by scoreFactor() and does not count as correct.
     */
    public function validateAnswer(mixed $answer, Question $question): bool
    {
        $distance = $this->distanceKm($answer, $question);

        if ($distance === null) {
            return false;
        }

        return $distance <= $this->thresholdKm($question);
    }

    public function scoreFactor(mixed $answer, Question $question): float
    {
        $distance = $this->distanceKm($answer, $question);

        if ($distance === null) {
            return 0.0;
        }

        $threshold = $this->thresholdKm($question);
        $max = $this->maxDistanceKm($question);

        if ($distance <= $threshold) {
            return 1.0;
        }

        if ($distance >= $max) {
            return 0.0;
        }

        $factor = 1.0 - (($distance - $threshold) / ($max - $threshold));

        return max(0.0, min(1.0, $factor));
    }

    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int
    {
        return (int) round($question->points * $this->scoreFactor($question->correct_answer, $question));
    }

    /**
     * Options are optional. When present, center/zoom/max_distance_km/threshold_km
     * must be well-formed so map rendering and scoring stay safe.
     */
    public function validateOptions(array $options): bool
    {
        if (array_key_exists('center', $options)) {
            if ($this->coordinates($options['center']) === null) {
                return false;
            }
        }

        if (array_key_exists('zoom', $options)) {
            if (! is_numeric($options['zoom']) || $options['zoom'] < 0) {
                return false;
            }
        }

        $max = null;
        if (array_key_exists('max_distance_km', $options)) {
            if (! is_numeric($options['max_distance_km']) || $options['max_distance_km'] <= 0) {
                return false;
            }
            $max = (float) $options['max_distance_km'];
        }

        if (array_key_exists('threshold_km', $options)) {
            if (! is_numeric($options['threshold_km']) || $options['threshold_km'] < 0) {
                return false;
            }
            if ($max !== null && $options['threshold_km'] >= $max) {
                return false;
            }
        }

        return true;
    }

    /**
     * Great-circle distance between two points in kilometres.
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Distance in km between the submitted answer and the correct location,
     * or null when either coordinate pair is missing/invalid.
     */
    private function distanceKm(mixed $answer, Question $question): ?float
    {
        $guess = $this->coordinates($answer);
        $correct = $this->coordinates($question->correct_answer);

        if ($guess === null || $correct === null) {
            return null;
        }

        return self::haversine($guess['lat'], $guess['lng'], $correct['lat'], $correct['lng']);
    }

    /**
     * Extract a validated [lat, lng] pair from a value, or null when invalid.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coordinates(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['lat'], $value['lng'])) {
            return null;
        }

        $lat = $value['lat'];
        $lng = $value['lng'];

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function maxDistanceKm(Question $question): float
    {
        $options = $question->options ?? [];

        if (isset($options['max_distance_km']) && is_numeric($options['max_distance_km']) && $options['max_distance_km'] > 0) {
            return (float) $options['max_distance_km'];
        }

        return (float) config('quiz.geo_guesser.max_distance_km', 2000);
    }

    private function thresholdKm(Question $question): float
    {
        $options = $question->options ?? [];
        $max = $this->maxDistanceKm($question);

        $threshold = (float) config('quiz.geo_guesser.threshold_km', 0);
        if (isset($options['threshold_km']) && is_numeric($options['threshold_km']) && $options['threshold_km'] >= 0) {
            $threshold = (float) $options['threshold_km'];
        }

        return min($threshold, $max);
    }
}
