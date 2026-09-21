<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextEntry::make('type')
                    ->label('Tipo'),
                TextEntry::make('user.name')
                    ->label('Usuario')
                    ->placeholder('-'),
                TextEntry::make('name')
                    ->label('Nombre'),
                TextEntry::make('description')
                    ->label('Descripción')
                    ->placeholder('-'),
                TextEntry::make('occurred_at')
                    ->label('Ocurrió en')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('ip_address')
                    ->label('IP')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->label('Navegador')
                    ->placeholder('-'),
            ]);
    }
}
