<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Departament;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
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
                TextColumn::make('activity_description')
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
                        if (!$state) return '0h 0m';
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
                        DatePicker::make('date_from')
                            ->label('Fecha desde')
                            ->default(now()->subMonth())
                            ->required(),
                        DatePicker::make('date_to')
                            ->label('Fecha hasta')
                            ->default(now())
                            ->required(),
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn () => Departament::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Todos los departamentos'),
                    ])
                    ->exports([
                        ExcelExport::make('asistencias')
                            ->withFilename('asistencias-' . date('Y-m-d'))
                            ->withColumns([
                                'id',
                                'user.name',
                                'department.name',
                                'date',
                                'check_in_at',
                                'check_out_at',
                                'total_minutes',
                                'status',
                            ])
                            ->modifyQueryUsing(function (Builder $query, $action) {
                                $data = $action->getFormData();

                                return $query
                                    ->when(
                                        $data['date_from'] ?? null,
                                        fn ($q, $date) => $q->whereDate('date', '>=', $date)
                                    )
                                    ->when(
                                        $data['date_to'] ?? null,
                                        fn ($q, $date) => $q->whereDate('date', '<=', $date)
                                    )
                                    ->when(
                                        $data['department_id'] ?? null,
                                        fn ($q, $deptId) => $q->where('department_id', $deptId)
                                    );
                            }),
                    ]),
            ]);
    }
}