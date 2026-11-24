<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Models\Departament;
use App\Models\Report;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ReportsTable
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

                TextColumn::make('attendances_count')->label('Jornadas')->sortable(),
                TextColumn::make('total_minutes')->label('Horas trabajadas')->formatStateUsing(fn ($state) => round(($state ?? 0) / 60, 2))->sortable(),
                TextColumn::make('late_minutes')->label('Minutos de tardanza')->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(function (): array {
                        $actor = auth()->user();
                        $query = Departament::query();
                        if ($actor && ! $actor->isSuperAdmin()) {
                            $query->whereKey($actor->isManager() ? $actor->managedDepartmentIds() : [(int) $actor->department_id]);
                        }

                        return $query->orderBy('name')->pluck('name', 'id')->all();
                    })
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $departmentId): Builder => $query->whereHas(
                            'user',
                            fn (Builder $userQuery): Builder => $userQuery->where('department_id', $departmentId),
                        ),
                    )),
                SelectFilter::make('user_id')->label('Empleado')->options(function (): array {
                    $query = User::query();
                    auth()->user()?->applyTeamScopeTo($query, 'users.id');

                    return $query->orderBy('name')->pluck('name', 'id')->all();
                })->searchable(),
                SelectFilter::make('manager_id')->label('Gerente')->options(function (): array {
                    $actor = auth()->user();
                    $query = User::query()->whereHas('departmentsManaged');
                    if ($actor && ! $actor->isSuperAdmin()) {
                        $query->whereHas('departmentsManaged', fn (Builder $departments): Builder => $departments
                            ->whereIn('departaments.id', $actor->managedDepartmentIds()));
                    }

                    return $query->orderBy('name')->pluck('name', 'id')->all();
                })->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $query, int|string $managerId): Builder => $query->whereHas(
                        'user.department.managers',
                        fn (Builder $managerQuery): Builder => $managerQuery->whereKey($managerId),
                    ),
                )),
                Filter::make('attendance_dates')
                    ->label('Fechas de asistencia')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta')->afterOrEqual('from'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->whereHas(
                        'attendances',
                        fn (Builder $attendanceQuery): Builder => $attendanceQuery
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('date', '<=', $date)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make('export')
                    ->label('Exportar Excel')
                    ->authorize('viewAny', Report::class)
                    ->exports([
                        ExcelExport::make('reportes')->fromTable()->withFilename('reportes-'.date('Y-m-d')),
                    ]),
            ]);
    }
}
