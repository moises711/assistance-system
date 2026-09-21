<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextEntry::make('user.name')
                    ->label('Usuario'),
                TextEntry::make('department.name')
                    ->label('Departamento'),
                TextEntry::make('date')
                    ->label('Fecha')
                    ->date(),
                TextEntry::make('start_time')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('end_time')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('total_hours')
                    ->label('Horas totales'),
                TextEntry::make('overtime')
                    ->label('Horas extra'),
                TextEntry::make('missingtime')
                    ->label('Horas faltantes'),
                TextEntry::make('status')
                    ->label('Estado')
                    ->badge(),
            ]);
    }
}
