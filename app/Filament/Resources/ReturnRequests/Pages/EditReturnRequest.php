<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Support\Yetki;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReturnRequest extends EditRecord
{
    protected static string $resource = ReturnRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->authorize(fn () => Yetki::yonetici()),
        ];
    }
}
