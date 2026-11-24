<?php

namespace App\Filament\Resources\Calendars;

use App\Filament\Resources\Calendars\Pages\CreateCalendar;
use App\Filament\Resources\Calendars\Pages\EditCalendar;
use App\Filament\Resources\Calendars\Pages\ListCalendars;
use App\Filament\Resources\Calendars\Pages\ViewCalendar;
use App\Filament\Resources\Calendars\Schemas\CalendarForm;
use App\Filament\Resources\Calendars\Schemas\CalendarInfolist;
use App\Filament\Resources\Calendars\Tables\CalendarsTable;
use App\Filament\Resources\Concerns\HasRoleBasedNavigation;
use App\Models\Calendar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CalendarResource extends Resource
{
    // use HasRoleBasedNavigation;

    protected static ?string $model = Calendar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CalendarForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CalendarInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalendarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && $user->can('viewAny', Calendar::class)) {
            return $user->applyTeamScopeTo($query, 'calendars.user_id');
        }

        return $query->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendars::route('/'),
            'create' => CreateCalendar::route('/create'),
            'view' => ViewCalendar::route('/{record}'),
            'edit' => EditCalendar::route('/{record}/edit'),
        ];
    }
}
