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
    public static function canView(): bool
    {
        return (auth()->user()?->can('Manage:AllRecords') ?? false)
            && (auth()->user()?->can('View:MainDashboardStats') ?? false);
    }

    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();

        $actor = auth()->user();
        $users = User::query();
        $attendances = Attendance::where('date', $today)->whereIn('status', ['presente', 'tarde']);
        $actor?->applyTeamScopeTo($users, 'users.id');
        $actor?->applyTeamScopeTo($attendances, 'attendances.user_id');
        $totalUsers = $users->count();
        $presentToday = $attendances->count();
        $departmentsCount = $actor?->isSuperAdmin()
            ? DB::table('departaments')->count()
            : (int) ($actor?->department_id !== null ? 1 : 0);

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
