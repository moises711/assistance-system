<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        $ownerId = (int) ($data['user_id'] ?? $actor->id);
        abort_unless($actor->canManageUserId($ownerId), 403);
        $data['user_id'] = $actor->isManager() ? $ownerId : $actor->id;
        $data['department_id'] = User::query()->whereKey($data['user_id'])->value('department_id');

        return $data;
    }
}
