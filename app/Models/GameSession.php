<?php

namespace App\Models;

use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory;

    public const OPEN_STATUSES = ['waiting', 'playing', 'reviewing'];

    public const IDLE_TIMEOUT_MINUTES = 120;

    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('updated_at', '<', now()->subMinutes(self::IDLE_TIMEOUT_MINUTES));
    }

    protected $fillable = [
        'quiz_id',
        'host_user_id',
        'join_code',
        'status',
        'current_question_index',
        'current_category_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'current_question_index' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GameSession $session) {
            if (empty($session->join_code)) {
                $session->join_code = strtoupper(Str::random(6));
            }
        });
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function currentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'current_category_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
