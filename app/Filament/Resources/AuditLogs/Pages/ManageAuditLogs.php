<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ManageAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
