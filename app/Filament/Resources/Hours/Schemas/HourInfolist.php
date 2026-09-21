<?php

namespace App\Filament\Resources\Hours\Schemas;

use Filament\Schemas\Schema;

class HourInfolist
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
