<?php

namespace App\Filament\Resources\Departaments\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class DepartamentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextInput::make('name')
                    ->label('Nombre del departamento')
                    ->required()
                    ->maxLength(255),
                TextInput::make('description')
                    ->label('Descripción del departamento')
                    ->nullable()
                    ->maxLength(255),
            ]);
    }
}
