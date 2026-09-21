<?php

namespace App\Enums;

enum ScheduleType: string
{
    case Fixed = 'fijo';
    case Flexible = 'flexible';
    case Rotating = 'rotativo';
    case Overnight = 'nocturno';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fijo',
            self::Flexible => 'Flexible',
            self::Rotating => 'Rotativo',
            self::Overnight => 'Nocturno',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(static fn (self $type): string => $type->value, self::cases()),
            array_map(static fn (self $type): string => $type->label(), self::cases()),
        );
    }
}
