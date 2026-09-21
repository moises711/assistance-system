<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
