<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Departament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function checkIn(User|Attendance $userOrAttendance, ?Carbon $at = null): Attendance
    {
        $user = $userOrAttendance instanceof Attendance ? $userOrAttendance->user : $userOrAttendance;
        $date = ($at ?? now())->toDateString();

        if ($userOrAttendance instanceof Attendance) {
            if (! empty($userOrAttendance->check_in_at)) {
                throw ValidationException::withMessages([
                    'check_in_at' => 'Ya existe una entrada para este registro.',
                ]);
            }

            $attendance = $userOrAttendance;
        } else {
            $attendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->first();

            if ($attendance && ! empty($attendance->check_in_at)) {
                throw ValidationException::withMessages([
                    'check_in_at' => 'Ya registraste tu entrada hoy.',
                ]);
            }

            $departmentId = Departament::query()->value('id') ?? Departament::query()->firstOrCreate([
                'name' => 'Sin departamento',
            ])->id;

            $attendance = $attendance ?? new Attendance([
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'date' => $date,
                'status' => 'pending',
            ]);
        }

        $attendance->check_in_at = $at ?? now();
        $attendance->date = $date;
        $attendance->status = 'present';
        $attendance->check_in_method = 'manual';
        $attendance->save();

        return $attendance->fresh();
    }

    public function startLunch(Attendance|User $attendanceOrUser, ?Carbon $at = null): Attendance
    {
        $attendance = $this->resolveAttendance($attendanceOrUser, $at);

        if (empty($attendance->check_in_at)) {
            throw ValidationException::withMessages([
                'lunch_start_at' => 'No puedes iniciar almuerzo sin una entrada.',
            ]);
        }

        if (! empty($attendance->lunch_start_at)) {
            throw ValidationException::withMessages([
                'lunch_start_at' => 'Ya se inició el almuerzo.',
            ]);
        }

        $attendance->lunch_start_at = $at ?? now();
        $attendance->save();

        return $attendance->fresh();
    }

    public function endLunch(Attendance|User $attendanceOrUser, ?Carbon $at = null): Attendance
    {
        $attendance = $this->resolveAttendance($attendanceOrUser, $at);

        if (empty($attendance->lunch_start_at)) {
            throw ValidationException::withMessages([
                'lunch_end_at' => 'No puedes finalizar almuerzo sin iniciarlo.',
            ]);
        }

        if (! empty($attendance->lunch_end_at)) {
            throw ValidationException::withMessages([
                'lunch_end_at' => 'El almuerzo ya fue finalizado.',
            ]);
        }

        $attendance->lunch_end_at = $at ?? now();
        $attendance->save();

        return $attendance->fresh();
    }

    public function checkOut(Attendance|User $attendanceOrUser, ?Carbon $at = null): Attendance
    {
        $attendance = $this->resolveAttendance($attendanceOrUser, $at);

        if (empty($attendance->check_in_at)) {
            throw ValidationException::withMessages([
                'check_out_at' => 'No puedes salir sin registrar la entrada.',
            ]);
        }

        if (! empty($attendance->check_out_at)) {
            throw ValidationException::withMessages([
                'check_out_at' => 'Ya se registró la salida.',
            ]);
        }

        $attendance->check_out_at = $at ?? now();
        $attendance->check_out_method = 'manual';
        $attendance->total_minutes = $this->calculateWorkedMinutes($attendance);
        $attendance->status = $attendance->total_minutes > 0 ? 'present' : 'pending';
        $attendance->save();

        return $attendance->fresh();
    }

    public function calculateWorkedMinutes(Attendance $attendance): int
    {
        if (empty($attendance->check_in_at) || empty($attendance->check_out_at)) {
            return 0;
        }

        $minutes = $attendance->check_in_at->diffInMinutes($attendance->check_out_at, false);

        if (! empty($attendance->lunch_start_at) && ! empty($attendance->lunch_end_at)) {
            $minutes += $attendance->lunch_start_at->diffInMinutes($attendance->lunch_end_at, false);
        }

        return max(0, $minutes);
    }

    public function calculateLateMinutes(Attendance $attendance): int
    {
        if (empty($attendance->check_in_at) || ! $attendance->user?->schedule) {
            return 0;
        }

        $deadline = $attendance->user->schedule->entryDeadline($attendance->date ?? $attendance->check_in_at);

        return max(0, $attendance->check_in_at->diffInMinutes($deadline, false));
    }

    public function validateActionOrder(Attendance $attendance, string $action): void
    {
        $valid = match ($action) {
            'check_in' => empty($attendance->check_in_at),
            'start_lunch' => ! empty($attendance->check_in_at) && empty($attendance->lunch_start_at),
            'end_lunch' => ! empty($attendance->check_in_at) && ! empty($attendance->lunch_start_at) && empty($attendance->lunch_end_at),
            'check_out' => ! empty($attendance->check_in_at) && empty($attendance->check_out_at),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                $action => 'La secuencia de asistencia no es válida.',
            ]);
        }
    }

    protected function resolveAttendance(Attendance|User $attendanceOrUser, ?Carbon $at = null): Attendance
    {
        if ($attendanceOrUser instanceof Attendance) {
            return $attendanceOrUser;
        }

        $date = ($at ?? now())->toDateString();
        $attendance = Attendance::query()
            ->where('user_id', $attendanceOrUser->id)
            ->whereDate('date', $date)
            ->first();

        if (! $attendance) {
            $departmentId = Departament::query()->value('id') ?? Departament::query()->firstOrCreate([
                'name' => 'Sin departamento',
            ])->id;

            $attendance = new Attendance([
                'user_id' => $attendanceOrUser->id,
                'department_id' => $departmentId,
                'date' => $date,
                'status' => 'pending',
            ]);
        }

        return $attendance;
    }
}
