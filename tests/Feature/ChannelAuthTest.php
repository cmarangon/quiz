<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

test('host channel authorizes the quiz host', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user, 'host')->create();

    // Directly test the channel authorization callback
    $result = Broadcast::channel('game.{sessionId}.host', function () {}); // register is already done in channels.php

    // Use the auth method directly
    $this->actingAs($user);
    $response = $this->post('/broadcasting/auth', [
        'channel_name' => 'private-game.'.$session->id.'.host',
    ]);
    $response->assertOk();
});

test('host channel rejects non-host user', function () {
    $other = User::factory()->create();
    $session = GameSession::factory()->create();

    $this->actingAs($other);
    $response = $this->post('/broadcasting/auth', [
        'channel_name' => 'private-game.'.$session->id.'.host',
    ]);
    $response->assertForbidden();
});
