<?php

namespace App\Filament\Resources\Ozellikler\Pages;

use App\Filament\Resources\Ozellikler\OzellikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOzellikler extends ListRecords
{
    protected static string $resource = OzellikResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni özellik')];
    }
}
