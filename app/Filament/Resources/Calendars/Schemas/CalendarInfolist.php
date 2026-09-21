<?php

namespace App\Filament\Resources\Calendars\Schemas;

use Filament\Schemas\Schema;

class CalendarInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                //
            ]);
    }
}
