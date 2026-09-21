<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    use VaryantlariEsitler;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->varyantAlanlariniAyir($data);
    }

    /** Oluşturunca doğrudan Varyantlar sekmesi: fiyat, stok ve görsel orada. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record, 'tab' => 'varyantlar']);
    }

    /** Ürün ve bütün kombinasyonları tek adımda (WooCommerce gibi). */
    protected function handleRecordCreation(array $data): Model
    {
        $product = parent::handleRecordCreation($data);

        $this->varyantlariEsitle($product);

        return $product;
    }
}
