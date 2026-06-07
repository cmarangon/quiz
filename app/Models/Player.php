<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'user_id',
        'nickname',
        'emoji',
        'score',
        'streak',
        'is_connected',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'streak' => 'integer',
            'is_connected' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * A player counts as online while their heartbeat is fresh. The player
     * screen beats every 5s; this tolerates two missed beats before the host
     * roster shows them as dropped. A locked phone stops the heartbeat, so the
     * player naturally goes stale and then reconnects on unlock.
     */
    public const PRESENCE_THRESHOLD_SECONDS = 12;

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::PRESENCE_THRESHOLD_SECONDS));
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
