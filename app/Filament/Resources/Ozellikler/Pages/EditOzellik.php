<?php

namespace App\Filament\Resources\Ozellikler\Pages;

use App\Filament\Resources\Ozellikler\OzellikResource;
use App\Models\Ozellik;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOzellik extends EditRecord
{
    protected static string $resource = OzellikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->hidden(fn (Ozellik $record) => $record->urunEksenleri()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
