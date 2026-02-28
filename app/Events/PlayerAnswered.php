<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PlayerAnswered implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public int $answeredCount,
        public int $totalPlayers,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'player.answered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'answered_count' => $this->answeredCount,
            'total_players' => $this->totalPlayers,
        ];
    }
}
