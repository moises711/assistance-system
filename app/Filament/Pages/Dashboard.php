<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MainDashboardStats;
use App\Models\Attendance;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Panel de control';

    public function getTitle(): string|Htmlable
    {
        $user = auth()->user();

        if ($user instanceof User) {
            if ($user->isAdmin()) {
                return 'Panel administrativo';
            }

            if ($user->isHumanResources()) {
                return 'Panel de recursos humanos';
            }

            if ($user->isSupervisor()) {
                return 'Panel del supervisor';
            }

            if ($user->isEmployee()) {
                return 'Mi asistencia';
            }

            if ($user->isIntern()) {
                return 'Mi práctica';
            }
        }

        return parent::getTitle();
    }

    /**
     * Acciones en la cabecera del Dashboard
     */
    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        // Validamos usando los métodos helper creados en User.php
        if (! $user instanceof User || (! $user->isEmployee() && ! $user->isIntern())) {
            return [];
        }

        return [
            Action::make('marcarAsistencia')
                ->label('Marcar Asistencia')
                ->icon('heroicon-o-clock')
                ->color('primary')
                ->action(function () use ($user) {
                    $today = Carbon::today()->toDateString();
                    $now = Carbon::now();
                    $timeFormatted = $now->format('H:i:s');

                    $attendance = Attendance::where('user_id', $user->id)
                        ->where('date', $today)
                        ->first();

                    if (! $attendance) {
                        // Entrada
                        Attendance::create([
                            'user_id' => $user->id,
                            'department_id' => $user->department_id,
                            'date' => $today,
                            'check_in_at' => $now,
                            'check_in_method' => 'manual',
                            'status' => 'presente',
                        ]);

                        Notification::make()
                            ->title('Entrada registrada')
                            ->body("Marcado a las {$timeFormatted}")
                            ->success()
                            ->send();
                    } elseif (! $attendance->check_out_at) {
                        // Salida
                        $attendance->update([
                            'check_out_at' => $now,
                            'check_out_method' => 'manual',
                        ]);

                        Notification::make()
                            ->title('Salida registrada')
                            ->body("Marcado a las {$timeFormatted}")
                            ->info()
                            ->send();
                    } else {
                        // Ya completó la jornada
                        Notification::make()
                            ->title('Jornada completada')
                            ->body('Ya has registrado tu entrada y salida del día de hoy.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            MainDashboardStats::class,
            AccountWidget::class,
        ];
    }
}
