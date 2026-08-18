<?php

namespace App\Filament\Resources\AdminAuditResource\Pages;

use App\Filament\Resources\AdminAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminAudits extends ListRecords
{
    protected static string $resource = AdminAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
