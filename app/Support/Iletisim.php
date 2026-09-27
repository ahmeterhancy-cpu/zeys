<?php

namespace App\Support;

/**
 * İletişim kanalları: WhatsApp bağlantısı ve harita adresleri.
 *
 * Numara panelde/`.env`'de yerel biçimde yazılır (05551800206);
 * wa.me uluslararası biçim ister (905551800206). Dönüşüm tek yerde
 * olsun diye burada.
 */
class Iletisim
{
    /** Yalnız rakamlar, uluslararası biçimde (90…). Yoksa null. */
    public static function whatsappNumarasi(): ?string
    {
        $ham = preg_replace('/\D/', '', (string) config('shop.iletisim.whatsapp'));

        if ($ham === '') {
            return null;
        }

        // 0555… → 90555…, 555… → 90555…, 90555… olduğu gibi
        if (str_starts_with($ham, '90')) {
            return $ham;
        }

        return '90'.ltrim($ham, '0');
    }

    /** Okunabilir biçim: 0555 180 02 06 */
    public static function whatsappGosterim(): ?string
    {
        $no = static::whatsappNumarasi();

        if (! $no) {
            return null;
        }

        $yerel = '0'.substr($no, 2); // 90 → 0

        return preg_replace('/^(\d{4})(\d{3})(\d{2})(\d{2})$/', '$1 $2 $3 $4', $yerel) ?: $yerel;
    }

    public static function whatsappBaglanti(?string $mesaj = null): ?string
    {
        $no = static::whatsappNumarasi();

        if (! $no) {
            return null;
        }

        $metin = $mesaj ?: (string) config('shop.iletisim.whatsapp_mesaj');

        return 'https://wa.me/'.$no.($metin !== '' ? '?text='.rawurlencode($metin) : '');
    }

    /**
     * Haritanın gömülü hâli. Ayarda tam bir gömme adresi verilmediyse
     * mağaza adresinden üretilir (anahtar gerektirmez).
     */
    public static function haritaGomme(): ?string
    {
        $ayar = trim((string) config('shop.iletisim.harita_embed'));

        if ($ayar !== '') {
            return $ayar;
        }

        $sorgu = trim((string) config('shop.iletisim.harita_sorgu'))
            ?: trim((string) config('shop.satici.adres'));

        if ($sorgu === '') {
            return null;
        }

        return 'https://www.google.com/maps?q='.rawurlencode($sorgu).'&hl=tr&z=17&output=embed';
    }

    /** "Yol tarifi al" bağlantısı. */
    public static function yolTarifi(): ?string
    {
        $adres = trim((string) config('shop.satici.adres'));

        if ($adres === '') {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($adres);
    }
}
