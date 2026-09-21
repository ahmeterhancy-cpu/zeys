<?php

namespace App\Filament\Resources\Products\Pages;

use App\Models\Product;
use App\Services\UrunVaryantlari;
use App\Support\Yetki;
use Filament\Notifications\Notification;

/**
 * Ürün oluştur/düzenle sayfalarında Varyantlar sekmesi: form verisinden
 * "eksenler" ve "varsayilan_*" alanları ayrılır (modelde sütun değiller),
 * ürün kaydedildikten sonra matris eşitlenir.
 */
trait VaryantlariEsitler
{
    /** @var array{eksenler: array, varsayilan: array}|null */
    protected ?array $varyantVerisi = null;

    /** Form verisinden varyant alanlarını ayır; kalan veri modele yazılır. */
    protected function varyantAlanlariniAyir(array $data): array
    {
        $this->varyantVerisi = [
            'eksenler' => $data['eksenler'] ?? [],
            'varsayilan' => [
                // Personel fiyat belirleyemez (alan kilitli; yine de sunucuda da yok sayılır)
                'fiyat' => Yetki::yonetici() ? ($data['varsayilan_fiyat'] ?? null) : null,
                'eski_fiyat' => Yetki::yonetici() ? ($data['varsayilan_eski_fiyat'] ?? null) : null,
                'stok' => $data['varsayilan_stok'] ?? 0,
            ],
        ];

        unset($data['eksenler'], $data['varsayilan_fiyat'], $data['varsayilan_eski_fiyat'], $data['varsayilan_stok']);

        return $data;
    }

    protected function varyantlariEsitle(Product $product): void
    {
        if ($this->varyantVerisi === null) {
            return;
        }

        $sonuc = app(UrunVaryantlari::class)->esitle(
            $product,
            $this->varyantVerisi['eksenler'],
            $this->varyantVerisi['varsayilan'],
        );

        $degisiklik = array_filter([
            $sonuc['eklenen'] ? $sonuc['eklenen'].' kombinasyon eklendi' : null,
            $sonuc['silinen'] ? $sonuc['silinen'].' kombinasyon silindi' : null,
            $sonuc['kaldirilan'] ? $sonuc['kaldirilan'].' kombinasyon satıştan kaldırıldı (sipariş geçmişi var)' : null,
        ]);

        if ($degisiklik !== []) {
            Notification::make()
                ->title('Varyantlar güncellendi')
                ->body(implode(' · ', $degisiklik).'. Toplam '.$sonuc['toplam'].' varyant.')
                ->success()
                ->send();
        }
    }
}
