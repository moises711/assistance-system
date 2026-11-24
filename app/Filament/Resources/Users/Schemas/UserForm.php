<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Departament;
use App\Models\Schedule;
use App\Models\WorkSite;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('last_name'),
                TextInput::make('dni')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),

                Select::make('roles')
                    ->label('Roles (Shield)')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $actor = auth()->user();
                            $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

                            if (! $actor || ! $actor->can('AssignRoles:User')) {
                                return $query->whereRaw('1 = 0');
                            }

                            if ($actor->hasRole($superAdminRole)) {
                                return $query;
                            }

                            $permissions = $actor->getAllPermissions()->pluck('name')->all();

                            return $query
                                ->where('name', '!=', $superAdminRole)
                                ->whereDoesntHave('permissions', fn (Builder $permissionsQuery): Builder => $permissionsQuery->whereNotIn('name', $permissions));
                        },
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->can('AssignRoles:User') ?? false)
                    ->required(fn (): bool => auth()->user()?->can('AssignRoles:User') ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->can('AssignRoles:User') ?? false),

                Select::make('schedule_id')
                    ->label('Horario')
                    ->options(fn () => Schedule::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:AttendanceSettings:User') ?? false))
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:AttendanceSettings:User') ?? false),
                Select::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Departament::query()
                        ->when(auth()->user()?->isManager() && ! auth()->user()?->isSuperAdmin(), fn (Builder $query) => $query->whereKey(auth()->user()->managedDepartmentIds()))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->default(fn () => auth()->user()?->isManager() && ! auth()->user()?->isSuperAdmin() ? auth()->user()->managedDepartmentIds()[0] ?? null : null)
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:AttendanceSettings:User') ?? false))
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:AttendanceSettings:User') ?? false),
                Select::make('departmentsManaged')
                    ->label('Departamentos a cargo')
                    ->relationship('departmentsManaged', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                Select::make('work_site_id')->label('Sede autorizada para marcaje GPS')
                    ->options(fn () => WorkSite::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->nullable()->helperText('Sin una sede activa asignada, el usuario podrá usar QR pero no marcar manualmente.')
                    ->disabled(fn (): bool => ! auth()->user()->can('AssignWorkSite:User'))
                    ->dehydrated(fn (): bool => auth()->user()->can('AssignWorkSite:User')),
                Toggle::make('is_active')
                    ->label('Usuario activo')
                    ->default(true)
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:UserSecurity') ?? false)
                        && ! (auth()->user()?->can('Deactivate:User') ?? false))
                    ->dehydrated(fn (): bool => (auth()->user()?->can('Manage:UserSecurity') ?? false)
                        || (auth()->user()?->can('Deactivate:User') ?? false)),
                Toggle::make('allows_remote')
                    ->label('Permitir trabajo remoto')
                    ->default(false)
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:AttendanceSettings:User') ?? false))
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:AttendanceSettings:User') ?? false),
                Toggle::make('require_activity_report')
                    ->label('Requerir reporte de actividad')
                    ->helperText('Si está activado, debe describir la actividad realizada al registrar la salida, también con QR. Desactivado: la descripción es opcional.')
                    ->default(false)
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:AttendanceSettings:User') ?? false))
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:AttendanceSettings:User') ?? false),
                DateTimePicker::make('email_verified_at')
                    ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:UserSecurity') ?? false))
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:UserSecurity') ?? false),
                TextInput::make('password')
                    ->password()
                    ->visible(fn (?string $operation): bool => $operation === 'create' || (auth()->user()?->can('Manage:UserSecurity') ?? false))
                    ->required(fn (?string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state, ?string $operation): bool => filled($state)
                        && ($operation === 'create' || (auth()->user()?->can('Manage:UserSecurity') ?? false)))
                    ->helperText('Al editar, deja este campo vacío para conservar la contraseña actual.')
                    ->nullable(),
                QrCodeInput::make('qr_code')
                    ->label('Código QR')
                    ->default(fn (): string => (string) Str::uuid())
                    ->helperText('Se genera automáticamente al crear el usuario.')
                    ->disabled(fn (?string $operation): bool => $operation === 'edit')
                    ->visible(fn (): bool => auth()->user()?->can('Manage:UserSecurity') ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->can('Manage:UserSecurity') ?? false),
            ]);
    }
}
