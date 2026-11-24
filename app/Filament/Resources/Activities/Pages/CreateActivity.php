<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateActivity extends CreateRecord
{
    protected static string $resource = ActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $user->id);
        abort_unless($user->canManageUserId($ownerId), 403);
        $data['user_id'] = $user->isManager() && $user->can('viewAny', User::class) ? $ownerId : $user->id;
        $data['type'] = 'work';

        return $data;
    }
}
