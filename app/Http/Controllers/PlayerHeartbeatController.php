<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Http\Response;

class PlayerHeartbeatController extends Controller
{
    /**
     * Record a player's presence heartbeat. Stateless and unauthenticated —
     * the session+player scoping is the only guard, mirroring the public game
     * channel's trust model. Called on an interval (and via sendBeacon) by the
     * player screen; a stale last_seen_at is what the host reads as "dropped".
     */
    public function __invoke(string $code, int $player): Response
    {
        $session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        $player = Player::where('id', $player)
            ->where('game_session_id', $session->id)
            ->firstOrFail();

        $player->update([
            'last_seen_at' => now(),
            'is_connected' => true,
        ]);

        return response()->noContent();
    }
}
