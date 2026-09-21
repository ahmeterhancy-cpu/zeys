<?php

namespace App\Filament\Resources\MenuOgeleri\Pages;

use App\Filament\Resources\MenuOgeleri\MenuOgesiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuOgesi extends CreateRecord
{
    protected static string $resource = MenuOgesiResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
