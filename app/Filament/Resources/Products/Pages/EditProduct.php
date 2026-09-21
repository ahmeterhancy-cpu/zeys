<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\UrunVaryantlari;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    use VaryantlariEsitler;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['eksenler'] = app(UrunVaryantlari::class)->formDurumu($this->record);
        $data['varsayilan_stok'] = 0;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->varyantAlanlariniAyir($data);
    }

    /**
     * Kayıtla birlikte kombinasyonlar eşitlenir — ayrı bir "kombinasyonları
     * üret" adımı yok (önceki sürümde vardı ve unutuluyordu).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);

        $this->varyantlariEsitle($record);

        return $record;
    }

    /** Varyant tablosu yeni kombinasyonlarla hemen görünsün diye sayfa yenilenir. */
    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vitrindeGor')
                ->label('Vitrinde gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Product $record) => url('/urun/'.$record->slug))
                ->openUrlInNewTab(),

            DeleteAction::make()->authorize(fn () => Yetki::yonetici())->label('Sil'),
        ];
    }
}
