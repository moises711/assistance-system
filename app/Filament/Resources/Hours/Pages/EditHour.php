<?php

namespace App\Filament\Resources\Hours\Pages;

use App\Filament\Resources\Hours\HourResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHour extends EditRecord
{
    protected static string $resource = HourResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $this->record->user_id);
        abort_unless($actor->canManageUserId($ownerId), 403);
        $data['user_id'] = $actor->isManager() ? $ownerId : $actor->id;

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
