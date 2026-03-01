<?php

namespace App\Events;

use App\Models\Category;
use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CategoryChanged implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public Category $category,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'category.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'category_id' => $this->category->id,
            'name' => $this->category->name,
            'theme' => $this->category->theme,
        ];
    }
}
