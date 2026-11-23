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
                TextEntry::make('check_in_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('check_out_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('total_minutes')
                    ->label('Minutos trabajados'),
                TextEntry::make('overtime_minutes')
                    ->label('Minutos extra'),
                TextEntry::make('missing_minutes')
                    ->label('Minutos faltantes'),
                TextEntry::make('daily_activity')->label('Actividad de la jornada')->placeholder('Sin actividad registrada')->columnSpanFull(),
                TextEntry::make('status')
                    ->label('Estado')
                    ->badge(),
            ]);
    }
}
