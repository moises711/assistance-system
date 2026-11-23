<?php

namespace App\Filament\Resources\WorkSites;

use App\Filament\Resources\WorkSites\Pages\ManageWorkSites;
use App\Models\WorkSite;
use App\Services\AttendanceLocationService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkSiteResource extends Resource
{
    protected static ?string $model = WorkSite::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Sedes y área GPS';

    protected static ?string $modelLabel = 'sede';

    protected static ?string $pluralModelLabel = 'sedes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre de la sede')->required()->maxLength(255),
            TextInput::make('radius_meters')->label('Radio autorizado (metros)')->numeric()->required()->default(100)->minValue(20)->maxValue(1000),
            Toggle::make('is_active')->label('Sede activa')->default(true),
            ViewField::make('location')->label('Capturar ubicación desde este teléfono')->view('filament.forms.gps-location')->default(null)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Sede')->searchable(), TextColumn::make('radius_meters')->label('Radio (m)'),
            TextColumn::make('latitude')->label('Latitud'), TextColumn::make('longitude')->label('Longitud'),
            TextColumn::make('capture_accuracy')->label('Precisión de captura (m)'), IconColumn::make('is_active')->label('Activa')->boolean(),
        ])->recordActions([EditAction::make()->mutateDataUsing(fn (array $data, WorkSite $record): array => app(AttendanceLocationService::class)->siteData(auth()->user(), $data, $record))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWorkSites::route('/')];
    }
}
