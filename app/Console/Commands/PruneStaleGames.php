<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use Illuminate\Console\Command;

class PruneStaleGames extends Command
{
    protected $signature = 'games:prune-stale';

    protected $description = 'Delete open game sessions that have been idle past the timeout';

    public function handle(): int
    {
        $count = GameSession::stale()->delete();

        $this->info("Pruned {$count} stale game session(s).");

        return self::SUCCESS;
    }
}
