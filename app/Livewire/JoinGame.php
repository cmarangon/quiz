<?php

namespace App\Livewire;

use App\Events\PlayerJoined;
use App\Models\GameSession;
use App\Models\Player;
use App\Support\PlayerEmojis;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.game')]
class JoinGame extends Component
{
    public string $code = '';

    public string $nickname = '';

    public string $emoji = '';

    public ?Player $player = null;

    public ?GameSession $session = null;

    public function mount(string $code): void
    {
        $this->code = strtoupper($code);
        $this->session = GameSession::where('join_code', $this->code)->first();
    }

    public function join()
    {
        $session = $this->session ?? GameSession::where('join_code', $this->code)->firstOrFail();

        if ($session->status !== 'waiting') {
            $this->addError('nickname', 'This game is no longer accepting players.');

            return;
        }

        $this->validate([
            'nickname' => 'required|string|min:1|max:50',
            'emoji' => ['required', Rule::in(PlayerEmojis::all())],
        ]);

        $nickname = $this->resolveUniqueNickname($session, trim($this->nickname));

        $player = Player::create([
            'game_session_id' => $session->id,
            'nickname' => $nickname,
            'emoji' => $this->emoji,
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
