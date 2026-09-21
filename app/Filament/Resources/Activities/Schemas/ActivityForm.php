<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la actividad')
                    ->required()
                    ->maxLength(255),
                TextInput::make('description')
                    ->label('Descripción de la actividad')
                    ->nullable()
                    ->maxLength(255),
            ]);
    }
}
