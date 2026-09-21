<?php

namespace App\Filament\Resources\Products\Pages;

use App\Models\Ozellik;
use App\Models\Product;
use App\Services\UrunVaryantlari;
use App\Support\Yetki;
use Filament\Notifications\Notification;

/**
 * Ürün oluştur/düzenle sayfalarında Varyantlar sekmesi: form verisinden
 * "secim" ve "varsayilan_*" alanları ayrılır (modelde sütun değiller),
 * ürün kaydedildikten sonra matris eşitlenir.
 */
trait VaryantlariEsitler
{
    /** @var array{eksenler: array, varsayilan: array}|null */
    protected ?array $varyantVerisi = null;

    /** Bu kayıtta kombinasyon eklendi/silindi/satıştan kalktı mı */
    protected bool $varyantDegisti = false;

    /** Form verisinden varyant alanlarını ayır; kalan veri modele yazılır. */
    protected function varyantAlanlariniAyir(array $data): array
    {
        $this->varyantVerisi = [
            'eksenler' => static::seciminEksenleri($data['secim'] ?? []),
            'varsayilan' => [
                // Personel fiyat belirleyemez (alan kilitli; yine de sunucuda da yok sayılır)
                'fiyat' => Yetki::yonetici() ? ($data['varsayilan_fiyat'] ?? null) : null,
                'eski_fiyat' => Yetki::yonetici() ? ($data['varsayilan_eski_fiyat'] ?? null) : null,
                'stok' => $data['varsayilan_stok'] ?? 0,
            ],
        ];

        unset($data['secim'], $data['varsayilan_fiyat'], $data['varsayilan_eski_fiyat'], $data['varsayilan_stok']);

        return $data;
    }

    /**
     * Çip seçimi (secim.{ozellik_id} => [değer id]) → eksen listesi,
     * kütüphane sırasıyla (vitrinde seçiciler bu sırayla görünür).
     */
    public static function seciminEksenleri(array $secim): array
    {
        $secim = array_filter($secim, fn ($degerler) => ! empty($degerler));

        return Ozellik::whereIn('id', array_keys($secim))->orderBy('sira')->orderBy('ad')->pluck('id')
            ->map(fn ($id) => ['ozellik_id' => $id, 'degerler' => array_values((array) $secim[$id])])
            ->all();
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

        $this->varyantDegisti = $degisiklik !== [];

        if ($degisiklik !== []) {
            Notification::make()
                ->title('Varyantlar güncellendi')
                ->body(implode(' · ', $degisiklik).'. Toplam '.$sonuc['toplam'].' varyant.')
                ->success()
                ->send();
        }
    }
}
