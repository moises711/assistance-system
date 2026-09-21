<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory , HasRoles;

    protected $fillable = [
        'name',
        'type',
        'start_time',
        'end_time',
        'tolerance_minutes',
        'maximum_late_minutes',
        'allows_late_entry',
        'working_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScheduleType::class,
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'tolerance_minutes' => 'integer',
            'maximum_late_minutes' => 'integer',
            'allows_late_entry' => 'boolean',
            'working_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), array_map('intval', $this->working_days ?? []), true);
    }

    public function entryDeadline(CarbonInterface $date): Carbon
    {
        return $this->timeOnDate($date, $this->start_time)
            ->addMinutes($this->tolerance_minutes);
    }

    public function durationMinutes(CarbonInterface $date): int
    {
        $start = $this->timeOnDate($date, $this->start_time);
        $end = $this->timeOnDate($date, $this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    public function lateMinutes(CarbonInterface $dateTime): int
    {
        $deadline = $this->entryDeadline($dateTime);

        return max(0, (int) $deadline->diffInMinutes($dateTime, false));
    }

    private function timeOnDate(CarbonInterface $date, CarbonInterface|string $time): Carbon
    {
        $timeValue = $time instanceof CarbonInterface ? $time->format('H:i:s') : (string) $time;

        return Carbon::parse($date->toDateString().' '.$timeValue, $date->getTimezone());
    }
}
