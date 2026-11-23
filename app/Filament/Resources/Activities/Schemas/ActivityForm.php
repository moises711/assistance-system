<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('user_id')->label('Usuario')->required()->default(fn () => auth()->id())
                ->options(fn () => auth()->user()?->isManager() && auth()->user()?->can('viewAny', User::class)
                    ? User::orderBy('name')->pluck('name', 'id') : User::whereKey(auth()->id())->pluck('name', 'id'))
                ->searchable(),
            Hidden::make('type')->default('work'),
            TextInput::make('name')->label('Nombre de la actividad')->required()->maxLength(255),
            DateTimePicker::make('occurred_at')->label('Fecha de actividad')->required()->default(now())->seconds(false),
            Textarea::make('description')->label('Descripción de la actividad')->maxLength(10000)->rows(5)->columnSpanFull(),
        ]);
    }
}
