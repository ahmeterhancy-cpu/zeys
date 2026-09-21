<?php

namespace App\Filament\Resources\Ozellikler\Pages;

use App\Filament\Resources\Ozellikler\OzellikResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOzellik extends CreateRecord
{
    protected static string $resource = OzellikResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
