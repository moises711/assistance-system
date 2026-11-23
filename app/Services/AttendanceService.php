<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function attendanceDate(User $user, ?Carbon $at = null): Carbon
    {
        $at = ($at ?? now())->copy()->setTimezone(config('app.timezone'));
        $schedule = $user->schedule;
        if ($schedule && $schedule->end_time->format('H:i:s') <= $schedule->start_time->format('H:i:s')
            && $at->format('H:i:s') < $schedule->start_time->format('H:i:s')) {
            $previous = Attendance::where('user_id', $user->id)
                ->whereDate('date', $at->copy()->subDay()->toDateString())
                ->whereNotNull('check_in_at')->exists();
            if ($previous) {
                return $at->subDay()->startOfDay();
            }
        }

        return $at->startOfDay();
    }

    public function today(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('date', $this->attendanceDate($user)->toDateString())->first();
    }

    public function checkIn(User|Attendance $subject, ?Carbon $at = null, ?string $notes = null, string $method = 'manual', ?array $location = null): Attendance
    {
        return $this->mutate($subject, $at, function (Attendance $attendance, User $user, Carbon $time) use ($notes, $method, $location, $subject): void {
            $this->validateActionOrder($attendance, 'check_in');
            if ($subject instanceof User && in_array($method, ['manual', 'web', 'gps'], true)) {
                $attendance->check_in_location = app(AttendanceLocationService::class)->validate($user, $location);
                $method = 'gps';
            }
            $notes = trim($notes ?? $attendance->daily_activity ?? '');
            $attendance->check_in_at = $time;
            $attendance->daily_activity = $notes === '' ? null : $notes;
            $attendance->check_in_method = $method;
            $attendance->late_minutes = $this->calculateLateMinutes($attendance);
            $attendance->status = $attendance->late_minutes > 0 ? 'tarde' : 'presente';
        });
    }

    public function startLunch(User|Attendance $subject, ?Carbon $at = null): Attendance
    {
        return $this->mutate($subject, $at, function (Attendance $attendance, User $user, Carbon $time): void {
            $this->validateActionOrder($attendance, 'start_lunch');
            $attendance->lunch_start_at = $time;
        });
    }

    public function endLunch(User|Attendance $subject, ?Carbon $at = null): Attendance
    {
        return $this->mutate($subject, $at, function (Attendance $attendance, User $user, Carbon $time): void {
            $this->validateActionOrder($attendance, 'end_lunch');
            $attendance->lunch_end_at = $time;
        });
    }

    public function checkOut(User|Attendance $subject, ?Carbon $at = null, string $method = 'manual', ?string $notes = null, ?array $location = null): Attendance
    {
        return $this->mutate($subject, $at, function (Attendance $attendance, User $user, Carbon $time) use ($method, $notes, $location, $subject): void {
            $this->validateActionOrder($attendance, 'check_out');
            $notes = trim($notes ?? $attendance->daily_activity ?? '');
            if ($user->require_activity_report && $notes === '') {
                throw ValidationException::withMessages(['notes' => 'Describe la actividad realizada antes de registrar la salida.']);
            }
            if ($subject instanceof User && in_array($method, ['manual', 'web', 'gps'], true)) {
                $attendance->check_out_location = app(AttendanceLocationService::class)->validate($user, $location);
                $method = 'gps';
            }
            $attendance->daily_activity = $notes === '' ? null : $notes;
            $attendance->check_out_at = $time;
            $attendance->check_out_method = $method;
            $attendance->total_minutes = $this->calculateWorkedMinutes($attendance);
        });
    }

    public function scan(User $user, ?string $notes = null, ?string $action = null): array
    {
        return DB::transaction(function () use ($user, $notes, $action): array {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $attendance = $this->today($user);
            $action ??= (! $attendance || ! $attendance->check_in_at) ? 'check_in' : 'check_out';
            if ($action === 'check_in') {
                return ['action' => 'check_in', 'attendance' => $this->checkIn($user, notes: $notes, method: 'qr')];
            }

            return ['action' => 'check_out', 'attendance' => $this->checkOut($user, method: 'qr', notes: $notes)];
        }, 3);
    }

    public function calculateWorkedMinutes(Attendance $attendance): int
    {
        if (! $attendance->check_in_at || ! $attendance->check_out_at) {
            return 0;
        }
        $minutes = $attendance->check_in_at->diffInMinutes($attendance->check_out_at, false);
        if ($attendance->lunch_start_at && $attendance->lunch_end_at) {
            $minutes -= $attendance->lunch_start_at->diffInMinutes($attendance->lunch_end_at, false);
        }

        return max(0, (int) $minutes);
    }

    public function calculateLateMinutes(Attendance $attendance): int
    {
        if (! $attendance->check_in_at || ! $attendance->user?->schedule) {
            return 0;
        }
        $deadline = $attendance->user->schedule->entryDeadline(
            Carbon::parse($attendance->date->toDateString(), config('app.timezone')));

        return max(0, (int) $deadline->diffInMinutes($attendance->check_in_at, false));
    }

    public function validateActionOrder(Attendance $attendance, string $action): void
    {
        $open = ! empty($attendance->check_in_at) && empty($attendance->check_out_at);
        $valid = match ($action) {
            'check_in' => empty($attendance->check_in_at),
            'start_lunch' => $open && empty($attendance->lunch_start_at),
            'end_lunch' => $open && ! empty($attendance->lunch_start_at) && empty($attendance->lunch_end_at),
            'check_out' => $open && (empty($attendance->lunch_start_at) || ! empty($attendance->lunch_end_at)),
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages([$action => 'La acción no corresponde al estado de tu jornada. Actualiza tu asistencia.']);
        }
    }

    protected function mutate(User|Attendance $subject, ?Carbon $at, callable $change): Attendance
    {
        return DB::transaction(function () use ($subject, $at, $change): Attendance {
            $userId = $subject instanceof Attendance ? $subject->user_id : $subject->id;
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $time = ($at ?? now())->copy()->setTimezone(config('app.timezone'));
            $date = $this->attendanceDate($user, $time)->toDateString();
            $attendance = $subject instanceof Attendance
                ? Attendance::whereKey($subject->id)->lockForUpdate()->firstOrFail()
                : Attendance::where('user_id', $user->id)->whereDate('date', $date)->lockForUpdate()->first();
            $attendance ??= new Attendance(['user_id' => $user->id, 'department_id' => $user->department_id, 'date' => $date, 'status' => 'pendiente']);
            $attendance->setRelation('user', $user);
            $change($attendance, $user, $time);
            $attendance->save();

            return $attendance->fresh();
        }, 3);
    }
}
