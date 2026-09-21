<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Calendar;
use App\Models\Departament;
use App\Models\Hour;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\CalendarPolicy;
use App\Policies\DepartamentPolicy;
use App\Policies\HourPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SecurityEventPolicy;
use App\Policies\UserPolicy;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use WallaceMartinss\FilamentSecurity\EventLog\Models\SecurityEvent;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de Políticas de acceso.
     */
    protected array $policies = [
        User::class => UserPolicy::class,
        Attendance::class => AttendancePolicy::class,
        Activity::class => ActivityPolicy::class,
        SecurityEvent::class => SecurityEventPolicy::class,
        Calendar::class => CalendarPolicy::class,
        Departament::class => DepartamentPolicy::class,
        Hour::class => HourPolicy::class,
        Report::class => ReportPolicy::class,
        Schedule::class => SchedulePolicy::class,

    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1. Registro de scripts
        FilamentAsset::register([
            Js::make('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'),
        ]);

        // 2. Registro de políticas en la Gate de Laravel
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 3. Bypass de acceso total para roles administrativos
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin'])) {
                return true;
            }

            return null;
        });

        // 4. Registrar evento de Login
        Event::listen(Login::class, function (Login $event): void {
            Activity::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'type' => 'login',
                'name' => 'Inicio de sesión',
                'description' => 'Inicio de sesión exitoso',
                'occurred_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                'metadata' => [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
            ]);
        });
    }
}