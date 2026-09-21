<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ScheduleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextEntry::make('name')
                    ->label('Nombre'),
                TextEntry::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (ScheduleType|string $state): string => $state instanceof ScheduleType
                        ? $state->label()
                        : ScheduleType::tryFrom($state)?->label() ?? $state),
                TextEntry::make('start_time')
                    ->label('Entrada')
                    ->time('H:i'),
                TextEntry::make('end_time')
                    ->label('Salida')
                    ->time('H:i'),
                TextEntry::make('tolerance_minutes')
                    ->label('Tolerancia')
                    ->suffix(' minutos'),
                TextEntry::make('maximum_late_minutes')
                    ->label('Tardanza máxima')
                    ->suffix(' minutos')
                    ->placeholder('-'),
                TextEntry::make('working_days')
                    ->label('Días laborables')
                    ->formatStateUsing(fn (array|string|null $state): string => is_array($state)
                        ? implode(', ', array_map(static fn (int|string $day): string => [
                            1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
                            5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
                        ][(int) $day] ?? (string) $day, $state))
                        : (string) ($state ?? '-')),
                TextEntry::make('allows_late_entry')
                    ->label('Permite entrada tardía')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
                TextEntry::make('is_active')
                    ->label('Estado')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo'),
            ]);
    }
}
