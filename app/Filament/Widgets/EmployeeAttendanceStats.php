<?php

namespace App\Filament\Widgets;

use App\Services\AttendanceService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class EmployeeAttendanceStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('View:EmployeeAttendanceStats') ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        $attendance = $user
            ? app(AttendanceService::class)->today($user)
            : null;

        return [
            Stat::make('Entrada de Hoy', $attendance?->check_in_at ? Carbon::parse($attendance->check_in_at)->format('H:i:s') : 'Pendiente')
                ->description('Hora de ingreso')
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->color($attendance?->check_in_at ? 'success' : 'gray'),

            Stat::make('Salida de Hoy', $attendance?->check_out_at ? Carbon::parse($attendance->check_out_at)->format('H:i:s') : 'Pendiente')
                ->description('Hora de salida')
                ->descriptionIcon('heroicon-m-check-circle', IconPosition::Before)
                ->color($attendance?->check_out_at ? 'info' : 'gray'),

            Stat::make('Estado', ucfirst($attendance?->status ?? 'Sin marcar'))
                ->description('Retraso: '.($attendance->late_minutes ?? 0).' min')
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->color($attendance && $attendance->late_minutes > 0 ? 'warning' : 'primary'),
        ];
    }
}
