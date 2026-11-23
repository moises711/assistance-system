<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Departament;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('user_id')->label('Usuario')->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->searchable()->preload()->live()->required(),
            Select::make('department_id')->label('Departamento')->options(fn () => Departament::orderBy('name')->pluck('name', 'id'))
                ->searchable()->preload(),
            DatePicker::make('date')->label('Fecha')->required(),
            Textarea::make('daily_activity')->label('Actividad de la jornada')->rows(3)->maxLength(10000)
                ->required(fn (Get $get): bool => filled($get('check_out_at')) && (bool) User::find($get('user_id'))?->require_activity_report)
                ->helperText('Obligatoria al registrar la salida cuando está activada para este usuario.')
                ->columnSpanFull(),
            DateTimePicker::make('check_in_at')->label('Entrada')->seconds(false),
            DateTimePicker::make('lunch_start_at')->label('Inicio de almuerzo')->seconds(false)->afterOrEqual('check_in_at'),
            DateTimePicker::make('lunch_end_at')->label('Fin de almuerzo')->seconds(false)->afterOrEqual('lunch_start_at'),
            DateTimePicker::make('check_out_at')->live()->label('Salida')->seconds(false)->afterOrEqual('check_in_at'),
            Select::make('status')->label('Estado')->options([
                'pendiente' => 'Pendiente', 'presente' => 'Presente', 'tarde' => 'Tarde', 'ausente' => 'Ausente',
            ])->default('pendiente')->required(),
        ]);
    }
}
