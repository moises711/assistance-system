<?php

namespace App\Models;

use Database\Factories\DepartamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Departament extends Model
{
    /** @use HasFactory<DepartamentFactory> */
    use HasFactory , HasRoles;

    protected $fillable = [
        'name',
        'description',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_manager', 'department_id', 'manager_id')
            ->withTimestamps();
    }
}
