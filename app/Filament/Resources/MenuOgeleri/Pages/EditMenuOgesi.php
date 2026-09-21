<?php

namespace App\Filament\Resources\MenuOgeleri\Pages;

use App\Filament\Resources\MenuOgeleri\MenuOgesiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuOgesi extends EditRecord
{
    protected static string $resource = MenuOgesiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
