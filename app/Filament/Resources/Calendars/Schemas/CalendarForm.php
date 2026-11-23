<?php

namespace App\Filament\Resources\Calendars\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CalendarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Evento')
                    ->description('Gestiona los detalles de la actividad')
                    ->schema([
                        Hidden::make('user_id')->default(fn () => auth()->id()),
                        TextInput::make('name')
                            ->label('Nombre del evento')
                            ->required()
                            ->maxLength(255),

                        DateTimePicker::make('due_at')
                            ->label('Fecha y Hora')
                            ->required()
                            ->native(false),

                        Textarea::make('description')
                            ->label('Descripción')->maxLength(255)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
