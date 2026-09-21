<?php

namespace App\Filament\Resources\Products\Pages;

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
            'eksenler' => UrunVaryantlari::seciminEksenleri($data['secim'] ?? []),
            'satirlar' => $data['kombinasyonlar'] ?? [],
            'varsayilan' => [
                // Personel fiyat belirleyemez (alan kilitli; yine de sunucuda da yok sayılır)
                'fiyat' => Yetki::yonetici() ? ($data['varsayilan_fiyat'] ?? null) : null,
                'eski_fiyat' => Yetki::yonetici() ? ($data['varsayilan_eski_fiyat'] ?? null) : null,
                'stok' => $data['varsayilan_stok'] ?? 0,
            ],
        ];

        unset($data['secim'], $data['kombinasyonlar'], $data['varsayilan_fiyat'], $data['varsayilan_eski_fiyat'], $data['varsayilan_stok']);

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

        $this->satirlariUygula($product, $this->varyantVerisi['satirlar']);

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

    /**
     * Oluştururken tabloda girilen satır değerleri (fiyat, stok, görsel,
     * satışta) ilgili varyanta yazılır. Boş hücre = başlangıç değeri kalır.
     */
    protected function satirlariUygula(Product $product, array $satirlar): void
    {
        if ($satirlar === []) {
            return;
        }

        $satirlar = collect($satirlar)->keyBy('anahtar');

        foreach ($product->variants()->with('optionValues')->get() as $varyant) {
            $satir = $satirlar->get(UrunVaryantlari::kombinasyonAnahtari(
                $varyant->optionValues->pluck('ozellik_degeri_id')->filter()->all()
            ));

            if (! $satir) {
                continue;
            }

            $gorsel = collect((array) ($satir['image'] ?? []))->first();

            $degisen = array_filter([
                // Personel fiyat belirleyemez
                'price' => Yetki::yonetici() && filled($satir['price'] ?? null) ? (float) $satir['price'] : null,
                'compare_at_price' => Yetki::yonetici() && filled($satir['compare_at_price'] ?? null) ? (float) $satir['compare_at_price'] : null,
                'stock' => filled($satir['stock'] ?? null) ? max(0, (int) $satir['stock']) : null,
                'image' => $gorsel ?: null,
            ], fn ($v) => $v !== null);

            if (array_key_exists('is_active', $satir)) {
                $degisen['is_active'] = (bool) $satir['is_active'];
            }

            if ($degisen !== []) {
                $varyant->update($degisen);
            }
        }
    }
}
