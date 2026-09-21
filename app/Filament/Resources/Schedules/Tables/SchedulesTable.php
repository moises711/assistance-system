<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ScheduleType|string $state): string => $state instanceof ScheduleType
                        ? $state->label()
                        : ScheduleType::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Entrada')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('tolerance_minutes')
                    ->label('Tolerancia')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('5xl'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
