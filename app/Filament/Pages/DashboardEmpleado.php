<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EmployeeAttendanceStats;
use App\Services\AttendanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DashboardEmpleado extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Mi Panel';

    protected static ?string $title = 'Mi Panel de Asistencia';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('page_DashboardEmpleado');
    }

    public function getHeaderWidgets(): array
    {
        return [
            EmployeeAttendanceStats::class,
        ];
    }

    public function getHeaderActions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }
        $service = app(AttendanceService::class);
        $attendance = $service->today($user);

        return [
            Action::make('entrada')->label('Registrar entrada')->icon('heroicon-o-arrow-right-on-rectangle')
                ->visible(! $attendance?->check_in_at)
                ->schema([ViewField::make('location')->view('filament.forms.gps-location')->extraAttributes(['data-auto-capture' => true])->required()])
                ->action(function (array $data) use ($user, $service): void {
                    $service->checkIn($user, method: 'web', location: $data['location'] ?? null);
                    Notification::make()->title('Entrada registrada')->success()->send();
                    $this->redirect(static::getUrl());
                }),
            Action::make('almuerzo')->label('Iniciar almuerzo')
                ->visible((bool) $attendance?->check_in_at && ! $attendance?->lunch_start_at && ! $attendance?->check_out_at)
                ->requiresConfirmation()->action(function () use ($user, $service): void {
                    $service->startLunch($user);
                    $this->redirect(static::getUrl());
                }),
            Action::make('finAlmuerzo')->label('Finalizar almuerzo')
                ->visible((bool) $attendance?->lunch_start_at && ! $attendance?->lunch_end_at && ! $attendance?->check_out_at)
                ->requiresConfirmation()->action(function () use ($user, $service): void {
                    $service->endLunch($user);
                    $this->redirect(static::getUrl());
                }),
            Action::make('salida')->label('Registrar salida')->color('warning')
                ->visible((bool) $attendance?->check_in_at && ! $attendance?->check_out_at
                    && (! $attendance?->lunch_start_at || (bool) $attendance?->lunch_end_at))
                ->schema([ViewField::make('location')->view('filament.forms.gps-location')->extraAttributes(['data-auto-capture' => true])->required(), Textarea::make('notes')->label('Actividad realizada')
                    ->required($user->require_activity_report)->maxLength(10000)->rows(3)])
                ->fillForm(['notes' => $attendance?->daily_activity])
                ->action(function (array $data) use ($user, $service): void {
                    $service->checkOut($user, method: 'web', notes: $data['notes'] ?? null, location: $data['location'] ?? null);
                    Notification::make()->title('Salida registrada')->success()->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
