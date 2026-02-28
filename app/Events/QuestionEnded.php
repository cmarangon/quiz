<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionEnded implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public Question $question,
        public array $scores,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'question.ended';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => $this->scores,
        ];
    }
}
