<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use Illuminate\Validation\ValidationException;

class AttendanceActivityService
{
    public function synchronize(Attendance $attendance): void
    {
        $description = trim($attendance->daily_activity ?? '');
        $activity = Activity::where('attendance_id', $attendance->id)->first();
        if (! $attendance->check_out_at || $description === '') {
            $this->remove($attendance);

            return;
        }
        if ($activity && $activity->user_id !== $attendance->user_id && $activity->hours()->exists()) {
            throw ValidationException::withMessages(['user_id' => 'La actividad tiene horas registradas; no se puede cambiar su usuario.']);
        }
        Activity::withoutEvents(fn () => Activity::updateOrCreate(['attendance_id' => $attendance->id], [
            'user_id' => $attendance->user_id,
            'type' => 'work',
            'name' => 'Actividad de la jornada '.$attendance->date->toDateString(),
            'description' => $description,
            'occurred_at' => $attendance->check_out_at,
            'metadata' => ['source' => 'attendance', 'method' => $attendance->check_out_method],
        ]));
    }

    public function remove(Attendance $attendance): void
    {
        $activity = Activity::where('attendance_id', $attendance->id)->first();
        if (! $activity) {
            return;
        }
        if ($activity->hours()->exists()) {
            throw ValidationException::withMessages(['daily_activity' => 'La actividad tiene horas registradas; no se puede eliminar ni vaciar.']);
        }
        Activity::withoutEvents(fn () => $activity->delete());
    }
}
