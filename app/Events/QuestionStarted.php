<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionStarted implements ShouldBroadcastNow
{
    public function __construct(
        public GameSession $session,
        public Question $question,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'question.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'body' => $this->question->body,
            'type' => $this->question->type,
            'options' => $this->question->options,
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'question_index' => $this->session->current_question_index,
        ];
    }
}
