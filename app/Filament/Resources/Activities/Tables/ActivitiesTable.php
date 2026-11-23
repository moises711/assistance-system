<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'login' => 'Inicio de sesión',
                        'work' => 'Trabajo',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attendance_id')->label('Asistencia vinculada')->placeholder('Registro manual'),
                TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->label('Ocurrió en')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(['work' => 'Trabajo', 'login' => 'Inicio de sesión']),
                SelectFilter::make('origen')->label('Origen')->options(['attendance' => 'Registrada al salir', 'manual' => 'Registro manual'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null,
                        fn (Builder $query, string $value): Builder => $value === 'attendance' ? $query->whereNotNull('attendance_id') : $query->whereNull('attendance_id')->where('type', 'work'))),
                Filter::make('fechas')->schema([
                    DatePicker::make('desde')->label('Desde'),
                    DatePicker::make('hasta')->label('Hasta')->afterOrEqual('desde'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['desde'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))
                    ->when($data['hasta'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v))),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('5xl'),
                EditAction::make()->visible(fn (Activity $record): bool => ActivityResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(fn (Activity $record): bool => ActivityResource::canDelete($record)),
                ]),
            ]);
    }
}
