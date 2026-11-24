<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $this->record->user_id);
        abort_unless($user->canManageUserId($ownerId), 403);
        $data['user_id'] = $user->isManager() && $user->can('viewAny', User::class) ? $ownerId : $user->id;
        $data['type'] = 'work';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
