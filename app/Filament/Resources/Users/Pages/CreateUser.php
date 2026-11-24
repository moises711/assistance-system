<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        if ($actor?->isManager() && ! $actor->isSuperAdmin()) {
            $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;
            abort_unless($actor->canManageDepartmentId($departmentId), 403);
            $data['department_id'] = $departmentId;
        }

        if (! $actor?->can('AssignRoles:User')) {
            unset($data['roles']);
        }

        if (! $actor?->can('Manage:AttendanceSettings:User')) {
            unset($data['schedule_id'], $data['allows_remote'], $data['require_activity_report']);
        }

        if (! $actor?->can('AssignWorkSite:User')) {
            unset($data['work_site_id']);
        }

        if (! $actor?->can('Manage:UserSecurity') && ! $actor?->can('Deactivate:User')) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $roles = $user->roles()->pluck('name')->all();
        $managedDepartmentIds = $user->departmentsManaged()->pluck('departaments.id')->all();

        AuditLog::create([
            'actor_user_id' => auth()->id(),
            'subject_user_id' => $user->id,
            'action' => 'user.created',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'new_values' => [
                'department_id' => $user->department_id,
                'schedule_id' => $user->schedule_id,
                'work_site_id' => $user->work_site_id,
                'is_active' => $user->is_active,
            ],
            'metadata' => ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
        ]);

        if ($roles !== []) {
            AuditLog::create([
                'actor_user_id' => auth()->id(),
                'subject_user_id' => $user->id,
                'action' => 'user.roles_assigned',
                'auditable_type' => $user::class,
                'auditable_id' => $user->id,
                'old_values' => ['roles' => []],
                'new_values' => ['roles' => $roles],
                'metadata' => ['ip' => request()->ip()],
            ]);
        }

        if ($managedDepartmentIds !== []) {
            AuditLog::create([
                'actor_user_id' => auth()->id(),
                'subject_user_id' => $user->id,
                'action' => 'user.departments_assigned',
                'auditable_type' => $user::class,
                'auditable_id' => $user->id,
                'old_values' => ['managed_department_ids' => []],
                'new_values' => ['managed_department_ids' => $managedDepartmentIds],
                'metadata' => ['ip' => request()->ip()],
            ]);
        }
    }
}
