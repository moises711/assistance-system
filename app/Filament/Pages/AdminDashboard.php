<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStats;
use App\Models\User;
use Filament\Pages\Page;

class AdminDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Admin Dashboard';

    protected static ?string $title = 'Panel Administrativo';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AdminStats::class,
        ];
    }
}
