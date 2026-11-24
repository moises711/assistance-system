<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string, mixed> */
    protected array $auditBefore = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        if ($actor?->isManager() && ! $actor->isSuperAdmin() && array_key_exists('department_id', $data)) {
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

        if (! $actor?->can('Manage:UserSecurity')) {
            unset($data['email_verified_at'], $data['password'], $data['qr_code']);
        }

        if (! $actor?->can('Manage:UserSecurity') && ! $actor?->can('Deactivate:User')) {
            unset($data['is_active']);
        }

        $this->auditBefore = [
            'roles' => $this->record->roles()->pluck('name')->sort()->values()->all(),
            'managed_department_ids' => $this->record->departmentsManaged()->pluck('departaments.id')->sort()->values()->all(),
            'department_id' => $this->record->department_id,
            'schedule_id' => $this->record->schedule_id,
            'work_site_id' => $this->record->work_site_id,
            'is_active' => $this->record->is_active,
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record->fresh();
        $roles = $user->roles()->pluck('name')->sort()->values()->all();

        if ($roles !== $this->auditBefore['roles']) {
            AuditLog::create([
                'actor_user_id' => auth()->id(),
                'subject_user_id' => $user->id,
                'action' => 'user.roles_assigned',
                'auditable_type' => $user::class,
                'auditable_id' => $user->id,
                'old_values' => ['roles' => $this->auditBefore['roles']],
                'new_values' => ['roles' => $roles],
                'metadata' => ['ip' => request()->ip()],
            ]);
        }

        $managedDepartmentIds = $user->departmentsManaged()->pluck('departaments.id')->sort()->values()->all();
        if ($managedDepartmentIds !== $this->auditBefore['managed_department_ids']) {
            AuditLog::create([
                'actor_user_id' => auth()->id(),
                'subject_user_id' => $user->id,
                'action' => 'user.departments_assigned',
                'auditable_type' => $user::class,
                'auditable_id' => $user->id,
                'old_values' => ['managed_department_ids' => $this->auditBefore['managed_department_ids']],
                'new_values' => ['managed_department_ids' => $managedDepartmentIds],
                'metadata' => ['ip' => request()->ip()],
            ]);
        }

        $after = [
            'department_id' => $user->department_id,
            'schedule_id' => $user->schedule_id,
            'work_site_id' => $user->work_site_id,
            'is_active' => $user->is_active,
        ];
        $before = array_intersect_key($this->auditBefore, $after);
        $oldValues = [];
        $newValues = [];

        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                $oldValues[$key] = $before[$key];
                $newValues[$key] = $value;
            }
        }

        if ($newValues !== []) {
            AuditLog::create([
                'actor_user_id' => auth()->id(),
                'subject_user_id' => $user->id,
                'action' => 'user.updated',
                'auditable_type' => $user::class,
                'auditable_id' => $user->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => ['ip' => request()->ip()],
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
