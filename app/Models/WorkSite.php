<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSite extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'latitude', 'longitude', 'radius_meters', 'capture_accuracy', 'captured_at', 'captured_by', 'is_active'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius_meters' => 'integer', 'capture_accuracy' => 'float', 'captured_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
