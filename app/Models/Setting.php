<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Panelden değiştirilebilen mağaza ayarları.
 *
 * Değerler açılışta `config('shop.*')` üzerine bindirilir (bkz.
 * AppServiceProvider). Böylece uygulamanın geri kalanı yalnızca
 * config() okur; ayarın .env'den mi panelden mi geldiğini bilmez.
 *
 * İki tuzak:
 *  1. Config önbelleği (`artisan optimize`) env()'i öldürür — bu yüzden
 *     değerler config dosyasında değil çalışma anında bindiriliyor.
 *  2. Ayar kaydedilince önbellek TAZELENMEZSE panelde değiştirilen
 *     değer sitede görünmez (başka projede saatler yedi). put() önbelleği
 *     her seferinde siler.
 */
class Setting extends Model
{
    protected $guarded = [];

    private const ONBELLEK = 'shop_ayarlari';

    /**
     * Panelden düzenlenebilen anahtarlar ve config karşılıkları.
     * Listede olmayan anahtar config'e BİNDİRİLMEZ — keyfi config
     * değerinin (örn. app.debug) panelden değiştirilmesini engeller.
     *
     * @var array<string, string> ayar anahtarı => config yolu
     */
    public const HARITA = [
        'kargo_ucret' => 'shop.kargo.ucret',
        'kargo_ucretsiz_esigi' => 'shop.kargo.ucretsiz_esigi',
        'kargo_firma' => 'shop.kargo.firma',
        'dusuk_stok_esigi' => 'shop.dusuk_stok_esigi',
        'siparis_bildirim_epostasi' => 'shop.siparis_bildirim_epostasi',
        'instagram' => 'shop.sosyal.instagram',
        'satici_unvan' => 'shop.satici.unvan',
        'satici_adres' => 'shop.satici.adres',
        'satici_telefon' => 'shop.satici.telefon',
        'satici_eposta' => 'shop.satici.eposta',
        'satici_mersis' => 'shop.satici.mersis',
        'satici_vergi_dairesi' => 'shop.satici.vergi_dairesi',
        'satici_vergi_no' => 'shop.satici.vergi_no',
    ];

    /** Sayısal olması gereken ayarlar — config'e doğru türde girsin. */
    private const SAYISAL = [
        'kargo_ucret' => 'float',
        'kargo_ucretsiz_esigi' => 'float',
        'dusuk_stok_esigi' => 'int',
    ];

    /** @return array<string, string|null> */
    public static function tumu(): array
    {
        try {
            return Cache::rememberForever(self::ONBELLEK, fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            // Tablo henüz yok (ilk migrate sırasında) — .env değerleriyle devam
            return [];
        }
    }

    /** @param  array<string, mixed>  $degerler */
    public static function kaydet(array $degerler): void
    {
        foreach ($degerler as $anahtar => $deger) {
            if (! array_key_exists($anahtar, self::HARITA)) {
                continue;
            }

            static::updateOrCreate(['key' => $anahtar], ['value' => $deger === null ? null : (string) $deger]);
        }

        Cache::forget(self::ONBELLEK);
        static::configeBindir();
    }

    /** Kayıtlı ayarları config'e bindir. Boş değer .env varsayılanını EZMEZ. */
    public static function configeBindir(): void
    {
        foreach (static::tumu() as $anahtar => $deger) {
            if (! isset(self::HARITA[$anahtar]) || $deger === null || $deger === '') {
                continue;
            }

            $deger = match (self::SAYISAL[$anahtar] ?? null) {
                'float' => (float) $deger,
                'int' => (int) $deger,
                default => $deger,
            };

            config([self::HARITA[$anahtar] => $deger]);
        }
    }

    /** Formu doldurmak için: şu anki etkin değerler (panel > .env). */
    public static function etkinDegerler(): array
    {
        $sonuc = [];

        foreach (self::HARITA as $anahtar => $yol) {
            $sonuc[$anahtar] = config($yol);
        }

        return $sonuc;
    }
}
