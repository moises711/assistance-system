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
use Illuminate\Support\Facades\Gate;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Panel de control';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Dashboard') ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Panel de control';
    }

    /**
     * Acciones en la cabecera del Dashboard
     */
    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $hasAttendanceToday = Attendance::query()
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->exists();

        return [
            Action::make('marcarAsistencia')
                ->label('Marcar Asistencia')
                ->icon('heroicon-o-clock')
                ->color('primary')
                ->authorize(fn (): bool => $user->can($hasAttendanceToday ? 'Update:Attendance' : 'Create:Attendance'))
                ->action(function () use ($user) {
                    $today = Carbon::today()->toDateString();
                    $now = Carbon::now();
                    $timeFormatted = $now->format('H:i:s');

                    $attendance = Attendance::where('user_id', $user->id)
                        ->where('date', $today)
                        ->first();

                    if (! $attendance) {
                        Gate::authorize('Create:Attendance');
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
                        Gate::authorize('Update:Attendance');
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
