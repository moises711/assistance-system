<?php

namespace App\Filament\Resources\Hours\Schemas;

use App\Models\Activity;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class HourForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->options(fn () => User::query()->tap(fn ($query) => auth()->user()?->applyTeamScopeTo($query, 'users.id'))->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->disabled(fn (): bool => ! auth()->user()?->isManager())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('activity_id')
                    ->label('Actividad')
                    ->options(fn () => Activity::query()
                        ->tap(fn ($query) => auth()->user()?->applyTeamScopeTo($query, 'activities.user_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('Fecha')
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Hora de inicio')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Hora de finalización')
                    ->seconds(false)
                    ->required()
                    ->after('start_time'),
            ]);
    }
}
