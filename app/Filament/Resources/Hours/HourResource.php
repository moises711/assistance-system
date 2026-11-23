<?php

namespace App\Filament\Resources\Hours;

use App\Filament\Resources\Concerns\HasRoleBasedNavigation;
use App\Filament\Resources\Hours\Pages\CreateHour;
use App\Filament\Resources\Hours\Pages\EditHour;
use App\Filament\Resources\Hours\Pages\ListHours;
use App\Filament\Resources\Hours\Pages\ViewHour;
use App\Filament\Resources\Hours\Schemas\HourForm;
use App\Filament\Resources\Hours\Schemas\HourInfolist;
use App\Filament\Resources\Hours\Tables\HoursTable;
use App\Models\Hour;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HourResource extends Resource
{
    // use HasRoleBasedNavigation;

    protected static ?string $model = Hour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Horas trabajadas';

    protected static ?string $modelLabel = 'hora trabajada';

    protected static ?string $pluralModelLabel = 'horas trabajadas';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return HourForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HourInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HoursTable::configure($table);
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
        if ($user && $user->isManager() && $user->can('viewAny', Hour::class)) {
            return $query;
        }

        return $query->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHours::route('/'),
            'create' => CreateHour::route('/create'),
            'view' => ViewHour::route('/{record}'),
            'edit' => EditHour::route('/{record}/edit'),
        ];
    }
}
