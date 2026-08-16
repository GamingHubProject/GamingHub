<?php

namespace App\Observers;

use App\Permissions\ScopedPermissionGenerator;
use GamingHub\Core\Models\Game;

class GameObserver
{
    public function __construct(protected ScopedPermissionGenerator $generator) {}

    public function created(Game $game): void
    {
        $this->generator->generateFor($game, 'game');
    }

    public function deleted(Game $game): void
    {
        $this->generator->deleteFor($game, 'game');
    }
}
