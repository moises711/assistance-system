<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Departament;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Departament::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('Fecha')
                    ->required(),
                DateTimePicker::make('activity_description')
                    ->label('Descripción de la Actividad')
                    ->required(),
                DateTimePicker::make('start_time')
                    ->label('Inicio')
                    ->seconds(false),
                DateTimePicker::make('end_time')
                    ->label('Fin')
                    ->seconds(false)
                    ->after('start_time'),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                    ])
                    ->default('pending')
                    ->required(),
            ]);
    }
}
