<?php

namespace App\Filament\Resources\Calendars\Pages;

use App\Filament\Resources\Calendars\CalendarResource;
use App\Filament\Widgets\FullCalendarWidget; // Importamos el nuevo widget
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalendars extends ListRecords
{
    protected static string $resource = CalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FullCalendarWidget::class, // Widget nativo de FullCalendar
        ];
    }
}