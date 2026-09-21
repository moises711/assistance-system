<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait HasRoleBasedNavigation
{
    /**
     * Oculta el recurso del menú lateral para roles no autorizados.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /**
     * Controla la autorización de lectura/acceso al recurso.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->is_active
            && in_array($user->role, [
                UserRole::Admin,
                UserRole::HumanResources,
                UserRole::Supervisor,
            ], true);
    }
}