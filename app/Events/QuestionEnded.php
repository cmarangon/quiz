<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionEnded implements ShouldBroadcastNow
{
    /**
     * @param  list<array{lat: float, lng: float, nickname: string, emoji: ?string}>  $guesses
     *                                                                                          Geo-guesser pins (empty for other question types), shown on the
     *                                                                                          spectator review map alongside the revealed correct location.
     * @param  array<string, list<array{nickname: string, emoji: ?string}>>  $distribution
     *                                                                                      For choice questions, players grouped by the option label they
     *                                                                                      picked (empty for other types); drives the spectator answer bars.
     */
    public function __construct(
        public GameSession $session,
        public Question $question,
        public array $scores,
        public array $guesses = [],
        public array $distribution = [],
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('game.'.$this->session->id)];
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
            'guesses' => $this->guesses,
            'distribution' => $this->distribution,
        ];
    }
}
