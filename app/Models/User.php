<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Atributos que se pueden asignar de forma masiva.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'dni',
        'email',
        'password',
        'require_activity_report',
        'qr_code',
        'is_active',
        'allows_remote',
        'work_site_id',
        'schedule_id',
        'department_id',

    ];

    /**
     * Atributos ocultos en la serialización.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'api_token_expires_at',
    ];

    /**
     * Conversión de tipos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'allows_remote' => 'boolean',
            'require_activity_report' => 'boolean',
            'api_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Determines whether the user can manage records across the organization.
     */
    public function isManager(): bool
    {
        return $this->isSuperAdmin() || $this->can('Manage:AllRecords');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    public function canManageUser(User $target): bool
    {
        if ((int) $this->id === (int) $target->id || $this->isSuperAdmin()) {
            return true;
        }

        return $this->isManager()
            && $target->department_id !== null
            && in_array((int) $target->department_id, $this->managedDepartmentIds(), true);
    }

    public function canManageUserId(int $userId): bool
    {
        if ((int) $this->id === $userId) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isManager()
            && static::query()->whereKey($userId)->whereIn('department_id', $this->managedDepartmentIds())->exists();
    }

    /** @return list<int> */
    public function managedDepartmentIds(): array
    {
        $ids = $this->departmentsManaged()->pluck('departaments.id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === [] && $this->department_id !== null) {
            $ids[] = (int) $this->department_id;
        }

        return array_values(array_unique($ids));
    }

    public function canManageDepartmentId(?int $departmentId): bool
    {
        return $this->isSuperAdmin()
            || ($departmentId !== null
                && $this->isManager()
                && in_array($departmentId, $this->managedDepartmentIds(), true));
    }

    public function applyTeamScopeTo(EloquentBuilder $query, string $ownerIdColumn = 'user_id'): EloquentBuilder
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        $departmentIds = $this->managedDepartmentIds();

        if ($this->isManager() && $departmentIds !== []) {
            return $query->whereIn($ownerIdColumn, static::query()
                ->select('id')
                ->whereIn('department_id', $departmentIds));
        }

        return $query->where($ownerIdColumn, $this->id);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->qr_code)) {
                $user->qr_code = (string) Str::uuid();
            }
        });
    }

    /**
     * Genera el URI del código QR del usuario.
     */
    public function qrCodeDataUri(): ?string
    {
        if (blank($this->qr_code)) {
            return null;
        }

        return (new Builder(
            writer: new SvgWriter,
            data: $this->qr_code,
            encoding: new Encoding('UTF-8'),
            size: 240,
            margin: 10,
        ))->build()->getDataUri();
    }

    /**
     * Controla el acceso al panel de Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roles()->exists();
    }

    /**
     * Compatibility helpers based on permissions rather than fixed role names.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            || $this->can('Manage:AllRecords');
    }

    public function isHumanResources(): bool
    {
        return $this->can('View:Rrhh');
    }

    public function isSupervisor(): bool
    {
        return $this->can('View:SupervisorDashboard');
    }

    public function isEmployee(): bool
    {
        return $this->can('View:DashboardEmpleado');
    }

    public function isIntern(): bool
    {
        return $this->isEmployee();
    }

    /**
     * Relaciones del Modelo.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departament::class, 'department_id');
    }

    public function departmentsManaged(): BelongsToMany
    {
        return $this->belongsToMany(Departament::class, 'department_manager', 'manager_id', 'department_id')
            ->withTimestamps();
    }

    public function hours(): HasMany
    {
        return $this->hasMany(Hour::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
