<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class RrhhStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('View:RrhhStats') ?? false;
    }

    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $actor = auth()->user();
        $attendances = Attendance::query();
        $users = User::query();
        $actor?->applyTeamScopeTo($attendances, 'attendances.user_id');
        $actor?->applyTeamScopeTo($users, 'users.id');

        $presentToday = (clone $attendances)->where('date', $today)
            ->whereIn('status', ['presente', 'tarde'])
            ->count();

        $lateToday = (clone $attendances)->where('date', $today)
            ->where('late_minutes', '>', 0)
            ->count();

        $totalActiveUsers = $users->where('is_active', 1)->count();

        return [
            Stat::make('Presentes Hoy', $presentToday)
                ->description('Asistencia registrada')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Tardanzas Hoy', $lateToday)
                ->description('Ingresos con retraso')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),

            Stat::make('Personal Activo', $totalActiveUsers)
                ->description('Total de empleados activos')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
