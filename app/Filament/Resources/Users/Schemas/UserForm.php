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

                // Configuración exacta para Filament Shield
                Select::make('roles')
                    ->label('Rol (Shield)')
                    ->relationship('roles', 'name') // Conecta con la tabla DB de Shield
                    ->preload()
                    ->searchable()
                    ->required(),

                Select::make('schedule_id')
                    ->label('Horario')
                    ->options(fn () => Schedule::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Departament::query()
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('work_site_id')->label('Sede autorizada para marcaje GPS')
                    ->options(fn () => WorkSite::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->nullable()->helperText('Sin una sede activa asignada, el usuario podrá usar QR pero no marcar manualmente.')
                    ->disabled(fn (): bool => ! auth()->user()->can('AssignWorkSite:User'))
                    ->dehydrated(fn (): bool => auth()->user()->can('AssignWorkSite:User')),
                Toggle::make('is_active')
                    ->label('Usuario activo')
                    ->default(true),
                Toggle::make('allows_remote')

                    ->label('Permitir trabajo remoto')
                    ->default(false),
                Toggle::make('require_activity_report')
                    ->label('Requerir reporte de actividad')
                    ->helperText('Si está activado, debe describir la actividad realizada al registrar la salida, también con QR. Desactivado: la descripción es opcional.')
                    ->default(false),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(fn (?string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Al editar, deja este campo vacío para conservar la contraseña actual.')
                    ->nullable(),
                QrCodeInput::make('qr_code')
                    ->label('Código QR')
                    ->default(fn (): string => (string) Str::uuid())
                    ->helperText('Se genera automáticamente al crear el usuario.')
                    ->disabled(fn (?string $operation): bool => $operation === 'edit')
                    ->dehydrated(),
            ]);
    }
}