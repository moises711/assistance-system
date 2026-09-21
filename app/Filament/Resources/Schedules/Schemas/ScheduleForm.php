<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipo de horario')
                    ->options(ScheduleType::options())
                    ->default(ScheduleType::Fixed->value)
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Hora de entrada')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Hora de salida')
                    ->seconds(false)
                    ->required(),
                TextInput::make('tolerance_minutes')
                    ->label('Tolerancia de entrada (minutos)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('maximum_late_minutes')
                    ->label('Tardanza máxima (minutos)')
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),
                CheckboxList::make('working_days')
                    ->label('Días laborables')
                    ->options([
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        7 => 'Domingo',
                    ])
                    ->columns(4)
                    ->default([1, 2, 3, 4, 5])
                    ->required(),
                Toggle::make('allows_late_entry')
                    ->label('Permitir entrada tardía')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Horario activo')
                    ->default(true),
            ]);
    }
}
