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

    protected $fillable = [
        'quiz_id',
        'host_user_id',
        'join_code',
        'status',
        'current_question_index',
        'current_category_id',
        'current_question_started_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'current_question_index' => 'integer',
            'current_question_started_at' => 'datetime',
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

    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('updated_at', '<', now()->subMinutes(self::IDLE_TIMEOUT_MINUTES));
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * The per-quiz presentation ("house") style for theme-independent screens.
     * One of: party-pop, game-show, bright-bouncy. Defaults to party-pop.
     */
    public function presentationStyle(): string
    {
        $allowed = ['party-pop', 'game-show', 'bright-bouncy'];
        $style = $this->quiz->settings['presentation_style'] ?? 'party-pop';

        return in_array($style, $allowed, true) ? $style : 'party-pop';
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
