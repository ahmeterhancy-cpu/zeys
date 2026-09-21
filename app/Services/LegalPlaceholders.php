<?php

namespace App\Services;

use App\Models\LegalDocument;

/**
 * Yasal metinlerdeki "[... GİRİLMEDİ]" yer tutucularını doldurur.
 *
 * Metinler seeder'da satıcı bilgileri boşken üretildi; bilgiler sonradan
 * girilince metinler kendiliğinden güncellenmez.
 *
 * İki kural:
 *  1. Metin YERİNDE değiştirilmez — yeni sürüm yayımlanır. Eski sürüme
 *     dayanan siparişlerin müşterisi O metni onayladı.
 *  2. Şablondan YENİDEN ÜRETİLMEZ — yalnızca yer tutucular değiştirilir.
 *     Yönetici metni elle düzenlemiş olabilir; şablondan üretmek onun
 *     düzenlemesini sessizce ezerdi.
 */
class LegalPlaceholders
{
    /** @return array<string, string|null> yer tutucu => config değeri */
    public function eslesmeler(): array
    {
        return [
            '[SATICI UNVANI GİRİLMEDİ]' => config('shop.satici.unvan'),
            '[ADRES GİRİLMEDİ]' => config('shop.satici.adres'),
            '[TELEFON GİRİLMEDİ]' => config('shop.satici.telefon'),
            '[E-POSTA GİRİLMEDİ]' => config('shop.satici.eposta'),
            '[MERSİS NO GİRİLMEDİ]' => config('shop.satici.mersis'),
        ];
    }

    /** Yer tutucu barındıran yürürlükteki metin sayısı. */
    public function bekleyenSayisi(): int
    {
        return LegalDocument::where('is_current', true)
            ->where('body', 'like', '%GİRİLMEDİ]%')
            ->count();
    }

    /**
     * Doldurulabilen yer tutucuları değiştirip yeni sürüm yayımlar.
     *
     * @return array{yayimlanan:int, eksik:array<string>} eksik: hâlâ boş olan bilgiler
     */
    public function doldur(): array
    {
        $degistir = array_filter($this->eslesmeler(), fn ($d) => filled($d));
        $eksik = array_keys(array_filter($this->eslesmeler(), fn ($d) => blank($d)));

        $yayimlanan = 0;

        if ($degistir === []) {
            return ['yayimlanan' => 0, 'eksik' => $eksik];
        }

        $belgeler = LegalDocument::where('is_current', true)
            ->where('body', 'like', '%GİRİLMEDİ]%')
            ->get();

        foreach ($belgeler as $belge) {
            $yeniGovde = strtr($belge->body, array_map(fn ($d) => e($d), $degistir));

            if ($yeniGovde === $belge->body) {
                continue; // bu metindeki yer tutucuların hiçbiri doldurulamadı
            }

            LegalDocument::publish($belge->slug, $belge->title, $yeniGovde);
            $yayimlanan++;
        }

        return ['yayimlanan' => $yayimlanan, 'eksik' => $eksik];
    }
}
