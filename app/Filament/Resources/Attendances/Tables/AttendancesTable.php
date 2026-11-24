<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
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
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),

                // Muestra SOLO día/mes/año sin 00:00:00
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                // Campo REAL de la base de datos: activity_description
                TextColumn::make('daily_activity')
                    ->label('Descripción de la Actividad')
                    ->limit(200)
                    ->sortable(),
                // Campo REAL de la base de datos: check_in_at
                TextColumn::make('check_in_at')
                    ->label('Inicio')
                    ->time('H:i:s')
                    ->placeholder('--:--:--')
                    ->sortable(),

                // Campo REAL de la base de datos: check_out_at
                TextColumn::make('check_out_at')
                    ->label('Fin')
                    ->time('H:i:s')
                    ->placeholder('--:--:--')
                    ->sortable(),

                // Transforma los total_minutes almacenados a "Xh Ym"
                TextColumn::make('total_minutes')
                    ->label('Horas')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '0h 0m';
                        }
                        $hours = floor($state / 60);
                        $minutes = $state % 60;

                        return "{$hours}h {$minutes}m";
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'presente' => 'success',
                        'tarde' => 'warning',
                        'ausente' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Filter::make('fechas')->schema([
                    DatePicker::make('desde')->label('Desde'),
                    DatePicker::make('hasta')->label('Hasta')->afterOrEqual('desde'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['desde'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                    ->when($data['hasta'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
                SelectFilter::make('user_id')->label('Empleado')->relationship('user', 'name')->searchable()->preload(),
                SelectFilter::make('department_id')->label('Departamento')->relationship('department', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Estado')->options([
                    'presente' => 'Presente', 'tarde' => 'Tarde', 'ausente' => 'Ausente', 'pendiente' => 'Pendiente',
                ]),
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
                    ->authorize('viewAny', Attendance::class)
                    ->exports([
                        ExcelExport::make('asistencias')->fromTable()
                            ->withFilename('asistencias-'.date('Y-m-d'))
                            ->withColumns([
                                Column::make('daily_activity')->heading('Actividad realizada'),
                                Column::make('late_minutes')->heading('Minutos de tardanza'),
                            ]),
                    ]),
            ]);
    }
}
