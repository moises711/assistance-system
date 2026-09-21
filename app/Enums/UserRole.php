<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case HumanResources = 'rrhh';
    case Supervisor = 'supervisor';
    case Employee = 'empleado';
    case Intern = 'pphh';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::HumanResources => 'Recursos humanos',
            self::Supervisor => 'Supervisor',
            self::Employee => 'Empleado',
            self::Intern => 'Practicante',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(static fn (self $role): string => $role->value, self::cases()),
            array_map(static fn (self $role): string => $role->label(), self::cases()),
        );
    }
}
