<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Schemas\Schema;

class ReportInfolist
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
