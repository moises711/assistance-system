<?php

namespace App\Filament\Resources\Hours\Pages;

use App\Filament\Resources\Hours\HourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHour extends CreateRecord
{
    protected static string $resource = HourResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $actor->id);
        abort_unless($actor->canManageUserId($ownerId), 403);
        $data['user_id'] = $actor->isManager() ? $ownerId : $actor->id;

        return $data;
    }
}
