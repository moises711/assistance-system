<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory , HasRoles;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'type',
        'name',
        'description',
        'occurred_at',
        'ip_address',
        'user_agent',
        'session_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Activity $activity): void {
            if ($activity->getOriginal('type') === 'login' || $activity->getOriginal('attendance_id') !== null || $activity->isDirty('attendance_id')) {
                throw ValidationException::withMessages(['description' => 'Esta actividad se modifica desde la asistencia vinculada.']);
            }
        });
        static::deleting(function (Activity $activity): void {
            if ($activity->attendance_id !== null || $activity->isLogin()) {
                throw ValidationException::withMessages(['description' => 'Esta actividad se elimina desde la asistencia vinculada.']);
            }
        });
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLogins(Builder $query): Builder
    {
        return $query->where('type', 'login');
    }

    public function isLogin(): bool
    {
        return $this->type === 'login';
    }

    public function hours(): HasMany
    {
        return $this->hasMany(Hour::class);
    }
}
