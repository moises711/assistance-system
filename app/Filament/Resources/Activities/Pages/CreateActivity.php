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
        if (! $user->isManager() || ! $user->can('viewAny', User::class)) {
            $data['user_id'] = $user->id;
        }
        $data['type'] = 'work';

        return $data;
    }
}
