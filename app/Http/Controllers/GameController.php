<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function create(Quiz $quiz): RedirectResponse
    {
        abort_unless($quiz->user_id === Auth::id(), 403);

        $session = GameSession::create([
            'quiz_id' => $quiz->id,
            'host_user_id' => Auth::id(),
            'status' => 'waiting',
        ]);

        return redirect()->route('game.host', $session->join_code);
    }
}
