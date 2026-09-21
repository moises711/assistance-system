<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
