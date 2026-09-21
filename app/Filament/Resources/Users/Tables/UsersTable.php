<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup; 
use Filament\Actions\DeleteBulkAction; 
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('dni')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (UserRole|string $state): string => $state instanceof UserRole
                        ? $state->label()
                        : UserRole::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('department_id')
                    ->label('Departamento')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qr_code')
                    ->label('Código QR')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                ExportAction::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->form([
                        DatePicker::make('fecha_desde')
                            ->label('Fecha Desde'),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha Hasta'),
                        Select::make('user_ids')
                            ->label('Usuarios')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => \App\Models\User::pluck('name', 'id')),
                    ])
                    ->exports([
                        ExcelExport::make('reporte')
                            ->withFilename('reporte-' . date('Y-m-d'))
                            ->modifyQueryUsing(function (Builder $query, $action) {
                                $data = $action->getFormData();

                                return $query
                                    ->when(
                                        $data['fecha_desde'] ?? null,
                                        fn ($q, $date) => $q->whereDate('created_at', '>=', $date)
                                    )
                                    ->when(
                                        $data['fecha_hasta'] ?? null,
                                        fn ($q, $date) => $q->whereDate('created_at', '<=', $date)
                                    )
                                    ->when(
                                        $data['user_ids'] ?? null,
                                        fn ($q, $userIds) => $q->whereIn('id', $userIds)
                                    );
                            }),
                    ]),
            ]);
    }
}