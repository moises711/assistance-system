<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MainDashboardStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();

        $totalUsers = User::count();
        $presentToday = Attendance::where('date', $today)->whereIn('status', ['presente', 'tarde'])->count();
        $departmentsCount = DB::table('departaments')->count();

        return [
            Stat::make('Total Usuarios', $totalUsers)
                ->description('Registrados en el sistema')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('primary'),

            Stat::make('Presentes Hoy', $presentToday)
                ->description('Asistencias del día')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->color('success'),

            Stat::make('Departamentos', $departmentsCount)
                ->description('Áreas operativas')
                ->descriptionIcon('heroicon-m-building-office-2', IconPosition::Before)
                ->color('info'),
        ];
    }
}
