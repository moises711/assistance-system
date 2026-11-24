<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
use Illuminate\Support\Arr;

class AttendanceObserver
{
    /** @var list<string> */
    private const AUDITED_FIELDS = [
        'user_id',
        'department_id',
        'date',
        'check_in_at',
        'lunch_start_at',
        'lunch_end_at',
        'check_out_at',
        'status',
        'daily_activity',
        'total_minutes',
        'overtime_minutes',
        'missing_minutes',
        'late_minutes',
        'early_leave_minutes',
    ];

    public function created(Attendance $attendance): void
    {
        $this->record(
            $attendance,
            'attendance.created',
            [],
            Arr::only($attendance->getAttributes(), self::AUDITED_FIELDS),
        );
    }

    public function updated(Attendance $attendance): void
    {
        $changed = Arr::only($attendance->getChanges(), self::AUDITED_FIELDS);

        if ($changed === []) {
            return;
        }

        $this->record(
            $attendance,
            'attendance.updated',
            Arr::only($attendance->getRawOriginal(), array_keys($changed)),
            $changed,
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function record(Attendance $attendance, string $action, array $before, array $after): void
    {
        $actor = request()->user() ?? auth()->user();

        AuditLog::create([
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'subject_user_id' => $attendance->user_id,
            'action' => $action,
            'auditable_type' => $attendance::class,
            'auditable_id' => $attendance->getKey(),
            'old_values' => $before,
            'new_values' => $after,
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }
}
