<?php

namespace App\Filament\Resources\WorkSites\Pages;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Services\AttendanceLocationService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkSites extends ManageRecords
{
    protected static string $resource = WorkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => app(AttendanceLocationService::class)->siteData(auth()->user(), $data)),
        ];
    }
}
