<?php

namespace App\Filament\Resources\Calendars\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class CalendarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')     
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                textColumn::make('user_id')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('due_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
               
            ])
            ->filters([
                //
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
