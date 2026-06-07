<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ReactionSent implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public string $emoji,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'reaction.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'emoji' => $this->emoji,
        ];
    }
}
