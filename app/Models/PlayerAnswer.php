<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAnswer extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_session_id',
        'question_id',
        'answer',
        'is_correct',
        'time_taken_ms',
        'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'json',
            'is_correct' => 'boolean',
            'time_taken_ms' => 'integer',
            'points_earned' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
