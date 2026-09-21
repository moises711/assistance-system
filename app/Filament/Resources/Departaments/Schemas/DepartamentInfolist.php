<?php

namespace App\Filament\Resources\Departaments\Schemas;

use Filament\Schemas\Schema;

class DepartamentInfolist
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
