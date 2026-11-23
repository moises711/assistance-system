<?php
namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $usersCount = User::count();
        $blockedIpsCount = DB::table('security_blocked_ips')->count();
        $securityEventsCount = DB::table('security_events')->count();

        return [
            Stat::make('Usuarios Totales', $usersCount)
                ->description('Registrados en el sistema')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('primary'),

            Stat::make('IPs Bloqueadas', $blockedIpsCount)
                ->description('Seguridad perimetral')
                ->descriptionIcon('heroicon-m-shield-exclamation', IconPosition::Before)
                ->color('danger'),

            Stat::make('Eventos de Seguridad', $securityEventsCount)
                ->description('Alertas o logs registrados')
                ->descriptionIcon('heroicon-m-bell-alert', IconPosition::Before)
                ->color('warning'),
        ];
    }
}