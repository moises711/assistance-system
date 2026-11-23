<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RrhhStats;
use App\Models\User;
use Filament\Pages\Page;

class Rrhh extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'RRHH';

    protected static ?string $title = 'Panel de Recursos Humanos';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isHumanResources() || $user->isAdmin());
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RrhhStats::class,
        ];
    }
}
