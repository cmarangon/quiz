<?php

return [
    'question_types' => [
        'multiple_choice' => \App\QuestionTypes\MultipleChoiceType::class,
        'true_false' => \App\QuestionTypes\TrueFalseType::class,
        'geo_guesser' => \App\QuestionTypes\GeoGuesserType::class,
    ],

    'geo_guesser' => [
        // Guesses within this radius (km) of the correct location earn full
        // points and count as correct. Beyond it, points decay linearly to
        // zero at max_distance_km. Both can be overridden per question via
        // the question's `options`.
        'threshold_km' => (float) env('QUIZ_GEO_THRESHOLD_KM', 50),
        'max_distance_km' => (float) env('QUIZ_GEO_MAX_DISTANCE_KM', 2000),
    ],
];
