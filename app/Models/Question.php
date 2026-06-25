<?php

namespace App\Models;

use App\Services\QuestionImageStorage;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'type',
        'body',
        'options',
        'correct_answer',
        'points',
        'time_limit_seconds',
        'order',
        'comment',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Question $question) {
            if ($question->type !== 'match_pairs') {
                return;
            }

            $storage = app(QuestionImageStorage::class);

            foreach (['left', 'right'] as $side) {
                foreach ($question->options[$side] ?? [] as $item) {
                    if (($item['kind'] ?? null) === 'image' && ! empty($item['value'])) {
                        $storage->delete($item['value']);
                    }
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'json',
            'points' => 'integer',
            'time_limit_seconds' => 'integer',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function effectiveTimeLimitSeconds(): int
    {
        return $this->time_limit_seconds
            ?? $this->category->quiz->settings['default_question_duration_seconds']
            ?? 30;
    }
}
