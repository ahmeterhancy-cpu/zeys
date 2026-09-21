<?php

namespace App\Filament\Resources\EpostaSablonlari\Pages;

use App\Filament\Resources\EpostaSablonlari\EpostaSablonuResource;
use App\Models\EpostaSablonu;
use Filament\Resources\Pages\ListRecords;

class ListEpostaSablonlari extends ListRecords
{
    protected static string $resource = EpostaSablonuResource::class;

    public function mount(): void
    {
        // Her e-posta listede görünsün: eksik satırlar boş (= varsayılan) açılır
        EpostaSablonu::eksikleriAc();

        parent::mount();
    }
}
