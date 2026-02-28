<?php

namespace App\Livewire;

use App\Events\PlayerJoined;
use App\Models\GameSession;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.game')]
class JoinGame extends Component
{
    public string $code = '';
    public string $nickname = '';
    public ?Player $player = null;

    public function mount(string $code): void
    {
        $this->code = strtoupper($code);
    }

    public function join()
    {
        $session = GameSession::where('join_code', $this->code)->firstOrFail();

        if ($session->status !== 'waiting') {
            $this->addError('nickname', 'This game is no longer accepting players.');
            return;
        }

        $this->validate([
            'nickname' => 'required|string|min:1|max:50',
        ]);

        $nickname = $this->resolveUniqueNickname($session, trim($this->nickname));

        $player = Player::create([
            'game_session_id' => $session->id,
            'nickname' => $nickname,
            'score' => 0,
            'streak' => 0,
            'is_connected' => true,
        ]);

        $this->player = $player;

        event(new PlayerJoined($session, $player));

        return redirect()->route('game.play', [
            'code' => $session->join_code,
            'player_id' => $player->id,
        ]);
    }

    private function resolveUniqueNickname(GameSession $session, string $nickname): string
    {
        $existing = $session->players()->pluck('nickname')->toArray();

        if (! in_array($nickname, $existing)) {
            return $nickname;
        }

        $counter = 2;
        while (in_array("{$nickname} {$counter}", $existing)) {
            $counter++;
        }

        return "{$nickname} {$counter}";
    }

    public function render()
    {
        return view('livewire.join-game')->title('Join Game');
    }
}
