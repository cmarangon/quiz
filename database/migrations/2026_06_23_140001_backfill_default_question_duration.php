<?php

use App\Models\Quiz;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Quiz::query()->each(function (Quiz $quiz) {
            if (array_key_exists('default_question_duration_seconds', $quiz->settings ?? [])) {
                return;
            }

            $quiz->update([
                'settings' => array_merge($quiz->settings ?? [], ['default_question_duration_seconds' => 30]),
            ]);
        });

        DB::table('questions')->update(['time_limit_seconds' => null]);
    }

    public function down(): void
    {
        // Data backfill is intentionally one-way: there is no record of which
        // questions had an explicit value before this ran.
    }
};
