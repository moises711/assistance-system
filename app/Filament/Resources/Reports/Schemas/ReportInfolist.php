<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextEntry::make('user.name')->label('Usuario'),
                TextEntry::make('attendances_count')->label('Jornadas registradas'),
                TextEntry::make('total_minutes')->label('Horas trabajadas')->formatStateUsing(fn ($state) => round(($state ?? 0) / 60, 2)),
                TextEntry::make('late_minutes')->label('Minutos de tardanza'),
            ]);
    }
}
