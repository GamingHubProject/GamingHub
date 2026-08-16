<?php

namespace App\Observers;

use App\Models\ServerGroup;
use App\Permissions\ScopedPermissionGenerator;

class ServerGroupObserver
{
    public function __construct(protected ScopedPermissionGenerator $generator) {}

    public function created(ServerGroup $serverGroup): void
    {
        $this->generator->generateFor($serverGroup, 'servergroup');
    }

    public function deleted(ServerGroup $serverGroup): void
    {
        $this->generator->deleteFor($serverGroup, 'servergroup');
    }
}
