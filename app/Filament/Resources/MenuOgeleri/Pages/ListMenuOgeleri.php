<?php

namespace App\Filament\Resources\MenuOgeleri\Pages;

use App\Filament\Resources\MenuOgeleri\MenuOgesiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuOgeleri extends ListRecords
{
    protected static string $resource = MenuOgesiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni bağlantı')];
    }
}
