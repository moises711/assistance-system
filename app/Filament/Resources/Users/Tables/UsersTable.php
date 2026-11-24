<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Departament;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

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
                TextColumn::make('roles.name')
                    ->label('Roles de Shield')
                    ->badge()
                    ->separator(','),
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
                TextColumn::make('workSite.name')
                    ->label('Sede de marcación')
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
                SelectFilter::make('department_id')->label('Departamento')->options(function (): array {
                    $actor = auth()->user();
                    $query = Departament::query();
                    if ($actor && ! $actor->isSuperAdmin()) {
                        $departmentIds = $actor->isManager() ? $actor->managedDepartmentIds() : [(int) $actor->department_id];
                        $query->whereKey($departmentIds);
                    }

                    return $query->orderBy('name')->pluck('name', 'id')->all();
                }),
                SelectFilter::make('is_active')->label('Estado')->options([1 => 'Activo', 0 => 'Inactivo']),
                SelectFilter::make('schedule_id')->label('Horario')->relationship('schedule', 'name')->searchable()->preload(),
                SelectFilter::make('work_site_id')->label('Sede')->relationship('workSite', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('5xl'),
                Action::make('sendInvitation')
                    ->label('Enviar invitación / restablecer contraseña')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (User $record): bool => ! str_ends_with(strtolower($record->email), '.test'))
                    ->requiresConfirmation()
                    ->authorize(fn (User $record): bool => auth()->user()?->isSuperAdmin()
                        || (auth()->user()?->can('SendInvitation:User') && auth()->user()?->canManageUser($record)))
                    ->action(function (User $record): void {
                        $status = Password::sendResetLink(['email' => $record->email]);
                        $sent = $status === Password::RESET_LINK_SENT;

                        Notification::make()
                            ->title($sent ? 'Invitación enviada' : 'No se pudo enviar el enlace')
                            ->body($sent ? 'El usuario recibirá un enlace para establecer o restablecer su contraseña.' : __($status))
                            ->status($sent ? 'success' : 'danger')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->authorize(fn (): bool => (auth()->user()?->can('Manage:AllRecords') ?? false)
                        && (auth()->user()?->can('viewAny', User::class) ?? false))
                    ->form([
                        DatePicker::make('fecha_desde')
                            ->label('Fecha Desde'),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha Hasta'),
                        Select::make('user_ids')
                            ->label('Usuarios')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => tap(User::query(), fn (Builder $query) => auth()->user()?->applyTeamScopeTo($query, 'users.id'))
                                ->orderBy('name')->pluck('name', 'id')),
                    ])
                    ->exports([
                        ExcelExport::make('reporte')
                            ->withFilename('reporte-'.date('Y-m-d'))
                            ->modifyQueryUsing(function (Builder $query, $action) {
                                $data = $action->getFormData();

                                $user = auth()->user();
                                $user?->applyTeamScopeTo($query, 'users.id');

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
