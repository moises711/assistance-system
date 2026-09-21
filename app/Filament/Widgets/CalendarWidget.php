<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Calendars\Schemas\CalendarForm;
use App\Models\Calendar;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms; // <-- IMPORTANTE
use Filament\Forms\Contracts\HasForms;         // <-- IMPORTANTE
use Filament\Widgets\Widget;

class FullCalendarWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms; // <-- IMPORTANTE

    protected string $view = 'filament.widgets.full-calendar-widget';

    protected int | string | array $columnSpan = 'full';

    public function getEvents(): array
    {
        return Calendar::all()->map(function (Calendar $calendar) {
            return [
                'id' => $calendar->id,
                'title' => $calendar->name,
                'start' => $calendar->due_at?->toIso8601String(),
                'end' => $calendar->due_at?->toIso8601String(),
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#2563eb',
            ];
        })->toArray();
    }

    public function editEventAction(): Action
    {
        return Action::make('editEvent')
            ->label('Editar Evento')
            ->modalHeading('Editar Detalles del Evento')
            ->record(fn (array $arguments) => Calendar::find($arguments['id'] ?? null))
            ->form(fn ($schema) => CalendarForm::configure($schema))
            ->action(function (Calendar $record, array $data): void {
                $record->update($data);
            });
    }

    public function createEventAction(): Action
    {
        return Action::make('createEvent')
            ->label('Nuevo Evento')
            ->modalHeading('Crear Nuevo Evento')
            ->mountUsing(function ($form, array $arguments) {
                if (isset($arguments['date'])) {
                    $form->fill([
                        'due_at' => $arguments['date'],
                    ]);
                }
            })
            ->form(fn ($schema) => CalendarForm::configure($schema))
            ->action(function (array $data): void {
                Calendar::create($data);
            });
    }
}