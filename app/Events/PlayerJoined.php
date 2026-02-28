<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PlayerJoined implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public Player $player,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'player.joined';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->player->id,
            'nickname' => $this->player->nickname,
            'player_count' => $this->session->players()->count(),
        ];
    }
}
