<?php

namespace App\Models;

use App\Services\AttendanceActivityService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory , HasRoles;

    protected $fillable = [
        'user_id',
        'department_id',
        'date',
        'Activity_description',
        'check_in_at',
        'lunch_start_at',
        'lunch_end_at',
        'check_out_at',
        'status',
        'total_minutes',
        'overtime_minutes',
        'missing_minutes',
        'late_minutes',
        'early_leave_minutes',
        'daily_activity',
        'check_in_method',
        'check_out_method',
        'start_time',
        'end_time',
        'overtime',
        'missingtime',
        'total_hours',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_location' => 'array',
            'check_out_location' => 'array',
            'check_in_at' => 'datetime',
            'lunch_start_at' => 'datetime',
            'lunch_end_at' => 'datetime',
            'check_out_at' => 'datetime',
            'total_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'missing_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'overtime' => 'integer',
            'missingtime' => 'integer',
            'total_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Attendance $attendance) => app(AttendanceActivityService::class)->synchronize($attendance));
        static::deleting(fn (Attendance $attendance) => app(AttendanceActivityService::class)->remove($attendance));
        static::saving(function (Attendance $attendance): void {
            $previous = null;
            foreach (['check_in_at', 'lunch_start_at', 'lunch_end_at', 'check_out_at'] as $field) {
                if ($attendance->$field && $previous && $attendance->$field->lessThan($previous)) {
                    throw ValidationException::withMessages([$field => 'Las marcas deben seguir el orden de la jornada.']);
                }
                $previous = $attendance->$field ?? $previous;
            }
            if (($attendance->lunch_start_at || $attendance->check_out_at) && ! $attendance->check_in_at) {
                throw ValidationException::withMessages(['check_in_at' => 'Registra primero la entrada.']);
            }
            if ($attendance->lunch_end_at && ! $attendance->lunch_start_at) {
                throw ValidationException::withMessages(['lunch_start_at' => 'Registra primero el inicio de almuerzo.']);
            }
            if ($attendance->check_out_at && $attendance->lunch_start_at && ! $attendance->lunch_end_at) {
                throw ValidationException::withMessages(['lunch_end_at' => 'Finaliza el almuerzo antes de registrar la salida.']);
            }
            if (! $attendance->exists || $attendance->isDirty(['user_id', 'date'])) {
                $duplicate = static::where('user_id', $attendance->user_id)->whereDate('date', $attendance->date->toDateString());
                if ($attendance->exists) {
                    $duplicate->whereKeyNot($attendance->id);
                }
                if ($duplicate->exists()) {
                    throw ValidationException::withMessages(['date' => 'Ya existe una asistencia para este usuario y fecha.']);
                }
            }
            if ($attendance->isDirty('user_id')) {
                $attendance->unsetRelation('user');
            }
            $service = app(AttendanceService::class);
            $attendance->total_minutes = $service->calculateWorkedMinutes($attendance);
            $attendance->late_minutes = $service->calculateLateMinutes($attendance);
            if ($attendance->check_out_at && $attendance->user?->require_activity_report && trim($attendance->daily_activity ?? '') === '') {
                throw ValidationException::withMessages(['daily_activity' => 'La actividad es obligatoria al registrar la salida.']);
            }
            if ($attendance->check_in_at) {
                $attendance->status = $attendance->late_minutes > 0 ? 'tarde' : 'presente';
            }
        });
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    public function activity(): HasOne
    {
        return $this->hasOne(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departament::class, 'department_id');
    }

    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('date', $date->toDateString());
    }
}
