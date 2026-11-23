<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class RoleDashboardStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Resumen del sistema';

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isEmployee() || $user->isIntern()) {
            $attendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            return [
                Stat::make('Estado de hoy', $attendance?->status ?? 'Sin marcar')
                    ->description($attendance?->check_in_at ? 'Entrada registrada' : 'Registra tu entrada')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color($attendance ? 'success' : 'warning'),
                Stat::make('Horas de hoy', round(($attendance?->total_minutes ?? 0) / 60, 2))
                    ->description('Horas registradas')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('info'),
                Stat::make('Horas extra', round(($attendance?->overtime_minutes ?? 0) / 60, 2))
                    ->description('Horas extra acumuladas hoy')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('warning'),
            ];
        }

        $today = Carbon::today();

        return [
            Stat::make('Usuarios activos', User::query()->where('is_active', true)->count())
                ->description('Usuarios habilitados')
                ->icon(Heroicon::OutlinedUsers)
                ->color('success'),
            Stat::make('Asistencias de hoy', Attendance::query()->whereDate('date', $today)->count())
                ->description('Registros del día')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('Pendientes', Attendance::query()->whereDate('date', $today)->where('status', 'pendiente')->count())
                ->description('Asistencias pendientes')
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
        ];
    }
}
