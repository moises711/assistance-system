<?php

namespace App\Filament\Resources\Departaments;

use App\Filament\Resources\Concerns\HasRoleBasedNavigation;
use App\Filament\Resources\Departaments\Pages\CreateDepartament;
use App\Filament\Resources\Departaments\Pages\EditDepartament;
use App\Filament\Resources\Departaments\Pages\ListDepartaments;
use App\Filament\Resources\Departaments\Pages\ViewDepartament;
use App\Filament\Resources\Departaments\Schemas\DepartamentForm;
use App\Filament\Resources\Departaments\Schemas\DepartamentInfolist;
use App\Filament\Resources\Departaments\Tables\DepartamentsTable;
use App\Models\Departament;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartamentResource extends Resource
{
    // use HasRoleBasedNavigation;

    protected static ?string $model = Departament::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Departamentos';

    protected static ?string $modelLabel = 'departamento';

    protected static ?string $pluralModelLabel = 'departamentos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DepartamentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DepartamentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartamentsTable::configure($table);
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
        $actor = auth()->user();

        if (! $actor) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->isSuperAdmin()) {
            return $query;
        }

        $departmentIds = $actor->isManager() ? $actor->managedDepartmentIds() : [(int) $actor->department_id];

        return $query->whereKey($departmentIds);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartaments::route('/'),
            'create' => CreateDepartament::route('/create'),
            'view' => ViewDepartament::route('/{record}'),
            'edit' => EditDepartament::route('/{record}/edit'),
        ];
    }
}
