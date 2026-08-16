<?php

namespace App\Observers;

use App\Permissions\ScopedPermissionGenerator;
use GamingHub\Core\Models\Server;

class ServerObserver
{
    public function __construct(protected ScopedPermissionGenerator $generator) {}

    public function created(Server $server): void
    {
        $this->generator->generateFor($server, 'server');
    }

    public function deleted(Server $server): void
    {
        $this->generator->deleteFor($server, 'server');
    }
}
