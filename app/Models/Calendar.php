<?php

namespace App\Models;

use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Calendar extends Model
{
    /** @use HasFactory<CalendarFactory> */
    use HasFactory , HasRoles;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function toCalendarEvent(): array
    {
        return [
            'id' => $this->id, 'title' => $this->name, 'description' => $this->description,
            'start' => $this->due_at?->toIso8601String(),
        ];
    }
}
