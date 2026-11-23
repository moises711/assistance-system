<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function isManager(): bool
    {
        $role = $this->role instanceof \BackedEnum ? $this->role->value : $this->role;

        return in_array($role, ['admin', 'rrhh', 'supervisor'], true)
            || $this->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'rrhh', 'supervisor']);
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
     * Helpers de verificación basados exclusivamente en roles de Spatie/Shield.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'Admin']);
    }

    public function isHumanResources(): bool
    {
        return $this->hasRole('HumanResources');
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole('Supervisor');
    }

    public function isEmployee(): bool
    {
        return $this->hasRole('Empleados');
    }

    public function isIntern(): bool
    {
        return $this->hasRole('Intern');
    }

    /**
     * Relaciones del Modelo.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
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
