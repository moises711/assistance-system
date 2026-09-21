<?php

namespace App\Models;

use Database\Factories\DepartamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
