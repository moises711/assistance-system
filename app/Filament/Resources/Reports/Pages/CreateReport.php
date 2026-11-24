<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReport extends CreateRecord
{
    protected static string $resource = ReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $actor->id);
        abort_unless($actor->canManageUserId($ownerId), 403);
        $data['user_id'] = $actor->isManager() ? $ownerId : $actor->id;

        return $data;
    }
}
