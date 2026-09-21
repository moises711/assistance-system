<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class AttendanceWidget extends Widget
{
    // Cambiar de 'protected static string $view' a 'protected string $view'
    protected string $view = 'filament.widgets.attendance-widget';

    // Ocupa todo el ancho en el Dashboard (esta sí puede ser 'int|string|array')
    protected int | string | array $columnSpan = 'full';

    public function checkIn()
    {
        $userId = auth()->id();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i:s');

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        if (! $attendance) {
            Attendance::create([
                'user_id' => $userId,
                'date' => $today,
                'check_in' => $now,
            ]);

            Notification::make()
                ->title('Entrada registrada')
                ->body("Hora: {$now}")
                ->success()
                ->send();
        } elseif (! $attendance->check_out) {
            $attendance->update([
                'check_out' => $now,
            ]);

            Notification::make()
                ->title('Salida registrada')
                ->body("Hora: {$now}")
                ->info()
                ->send();
        } else {
            Notification::make()
                ->title('Jornada finalizada')
                ->body('Ya registraste tu entrada y salida del día de hoy.')
                ->warning()
                ->send();
        }
    }

    public function getViewData(): array
    {
        $userId = auth()->id();
        $today = Carbon::today()->toDateString();

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        return [
            'attendance' => $attendance,
        ];
    }
}