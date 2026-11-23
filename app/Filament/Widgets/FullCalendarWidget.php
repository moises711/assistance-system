<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Calendars\Schemas\CalendarForm;
use App\Models\Calendar;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class FullCalendarWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.full-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    public function getEvents(): array
    {
        $query = Calendar::query()->whereNotNull('due_at');
        if (! auth()->user()?->isManager() || ! Gate::allows('viewAny', Calendar::class)) {
            $query->where('user_id', auth()->id());
        }

        return $query->get()->map(fn (Calendar $record): array => [...$record->toCalendarEvent(), 'extendedProps' => ['canEdit' => Gate::allows('update', $record)]])->all();
    }

    public function canCreateEvents(): bool
    {
        return Gate::allows('create', Calendar::class);
    }

    public function viewEventAction(): Action
    {
        return Action::make('viewEvent')->label('Detalle del evento')
            ->record(fn (array $arguments) => Calendar::findOrFail($arguments['id'] ?? 0))
            ->authorize(fn (Calendar $record): bool => Gate::allows('view', $record))
            ->schema([
                TextInput::make('name')->label('Evento')->disabled(),
                Textarea::make('description')->label('Descripción')->disabled(),
                DateTimePicker::make('due_at')->label('Fecha')->disabled(),
            ])
            ->fillForm(fn (Calendar $record): array => $record->attributesToArray())
            ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar');
    }

    public function editEventAction(): Action
    {
        return Action::make('editEvent')->label('Editar evento')
            ->record(fn (array $arguments) => Calendar::findOrFail($arguments['id'] ?? 0))
            ->authorize(fn (Calendar $record): bool => Gate::allows('update', $record))
            ->schema(fn (Schema $schema): Schema => CalendarForm::configure($schema))
            ->fillForm(fn (Calendar $record): array => $record->attributesToArray())
            ->action(function (Calendar $record, array $data): void {
                Gate::authorize('update', $record);
                unset($data['user_id']);
                $record->update($data);
                $this->dispatch('calendar-updated', events: $this->getEvents());
            });
    }

    public function createEventAction(): Action
    {
        return Action::make('createEvent')->label('Nuevo evento')->authorize('create', Calendar::class)
            ->schema(fn (Schema $schema): Schema => CalendarForm::configure($schema))
            ->fillForm(fn (array $arguments): array => ['due_at' => $arguments['date'] ?? null, 'user_id' => auth()->id()])
            ->action(function (array $data): void {
                Gate::authorize('create', Calendar::class);
                $data['user_id'] = auth()->id();
                Calendar::create($data);
                $this->dispatch('calendar-updated', events: $this->getEvents());
            });
    }
}
