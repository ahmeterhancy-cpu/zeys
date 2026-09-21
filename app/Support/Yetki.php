<?php

namespace App\Support;

/**
 * Panel yetkileri — iki rol:
 *
 *  - Yönetici (admin): her şey.
 *  - Personel: günlük operasyon — sipariş hazırlama/kargolama, iade
 *    lojistiği (kargo bekleniyor / geldi / değişim tamamlandı), stok ve
 *    ürün bilgisi, yorum onayı, stok talepleri.
 *
 * Personelin YAPAMADIKLARI: para hareketi (PayTR iadesi, "elle iade
 * ettim", ödenmiş siparişi iptal), fiyat değiştirme, silme, iade talebini
 * onaylama/reddetme (iade tutarını belirler), rapor, kupon, ayarlar,
 * kullanıcılar, yasal metinler, katalog yapısı, vitrin içeriği.
 *
 * Eylemlerde ->authorize() ile uygulanır: Filament yetkisiz eylemi hem
 * gizler hem de doğrudan Livewire çağrısıyla tetiklenmesini engeller
 * (->visible() yalnız gizlerdi).
 */
class Yetki
{
    public static function yonetici(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }
}
