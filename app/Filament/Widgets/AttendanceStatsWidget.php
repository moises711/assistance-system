<?php

namespace App\Filament\Widgets;

use App\Services\AttendanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class AttendanceStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('View:AttendanceStatsWidget') ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        $attendance = $user
            ? app(AttendanceService::class)->today($user)
            : null;

        return [
            Stat::make('Entrada', $attendance?->check_in_at ? Carbon::parse($attendance->check_in_at)->format('H:i:s') : 'Pendiente')
                ->description('Hora de ingreso')
                ->icon('heroicon-o-clock')
                ->color('success'),

            Stat::make('Salida', $attendance?->check_out_at ? Carbon::parse($attendance->check_out_at)->format('H:i:s') : 'Pendiente')
                ->description('Hora de salida')
                ->icon('heroicon-o-check-circle')
                ->color('info'),

            Stat::make('Estado', ucfirst($attendance?->status ?? 'Sin marcar'))
                ->description('Jornada del día')
                ->icon('heroicon-o-user-check')
                ->color($attendance ? 'primary' : 'gray'),
        ];
    }
}
