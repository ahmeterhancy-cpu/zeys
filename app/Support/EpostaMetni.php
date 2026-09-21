<?php

namespace App\Support;

use App\Models\EpostaSablonu;
use Throwable;

/**
 * Müşteri e-postalarının panelden değiştirilebilen metinleri.
 *
 * Değişen yalnız METİN: konu, başlık, ana paragraf ve alttaki not.
 * Sipariş tablosu, düğmeler, iade gerekçesi kutusu, sözleşme metinleri
 * gibi yapısal parçalar şablonda sabit — yönetici yanlışlıkla sipariş
 * dökümünü silemesin.
 *
 * Panelde boş bırakılan alan varsayılana döner. Metindeki {degisken}
 * yer tutucuları gönderim anında doldurulur; bilinmeyenler olduğu gibi
 * kalır (yazım hatası görünür olsun, sessizce silinmesin).
 */
class EpostaMetni
{
    /**
     * @var array<string, array{ad: string, degiskenler: list<string>, konu: string, baslik: string, metin: string, not: string}>
     */
    public const SABLONLAR = [
        'siparis-alindi' => [
            'ad' => 'Sipariş alındı',
            'degiskenler' => ['ad', 'siparis_no', 'magaza', 'cayma_gun'],
            'konu' => 'Siparişiniz alındı — {siparis_no}',
            'baslik' => 'Siparişiniz alındı',
            'metin' => 'Merhaba {ad}, siparişinizi aldık ve hazırlamaya başlıyoruz. Kargoya verildiğinde takip numarasını size ayrıca ileteceğiz.',
            'not' => 'Ürünü teslim aldıktan sonra {cayma_gun} gün içinde iade ya da değişim hakkınız var. Beden tutmazsa aynı modelin başka bedeniyle değiştirebilirsiniz.',
        ],
        'siparis-kargoda' => [
            'ad' => 'Sipariş kargoya verildi',
            'degiskenler' => ['ad', 'siparis_no', 'magaza', 'kargo_firma', 'takip_no'],
            'konu' => 'Siparişiniz kargoya verildi — {siparis_no}',
            'baslik' => 'Siparişiniz yola çıktı',
            'metin' => 'Merhaba {ad}, siparişiniz kargoya verildi.',
            'not' => 'Takip numarasının kargo firmasının sisteminde görünmesi birkaç saat sürebilir.',
        ],
        'stokta' => [
            'ad' => 'Beklenen ürün stokta',
            'degiskenler' => ['urun', 'magaza'],
            'konu' => 'Beklediğiniz ürün stokta — {urun}',
            'baslik' => 'Beklediğiniz ürün stokta',
            'metin' => 'Haber verilmesini istediğiniz beden yeniden satışta. Sınırlı sayıda olabileceği için beklemeden bakmanızı öneririz.',
            'not' => 'Bu bildirimi, ürün sayfasında "haber ver" dediğiniz için aldınız. Başka bir bildirim gönderilmeyecek.',
        ],
        'parola-sifirlama' => [
            'ad' => 'Parola sıfırlama',
            'degiskenler' => ['magaza', 'dakika'],
            'konu' => 'Parola sıfırlama — {magaza}',
            'baslik' => 'Parola sıfırlama',
            'metin' => 'Hesabınız için parola sıfırlama istendi. Yeni parola belirlemek için aşağıdaki düğmeye tıklayın. Bağlantı {dakika} dakika geçerlidir ve yalnızca bir kez kullanılabilir.',
            'not' => 'Bu isteği siz yapmadıysanız bu e-postayı yok sayın; parolanız değişmez.',
        ],
        'sozlesme-belgeleri' => [
            'ad' => 'Sözleşme belgeleri',
            'degiskenler' => ['ad', 'siparis_no', 'magaza'],
            'konu' => 'Sözleşme belgeleriniz — {siparis_no}',
            'baslik' => 'Sözleşme belgeleriniz',
            'metin' => 'Sipariş {siparis_no} için sipariş sırasında onayladığınız Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi aşağıdadır. Bu e-postayı saklamanızı öneririz.',
            'not' => '',
        ],
        'fatura' => [
            'ad' => 'Fatura',
            'degiskenler' => ['ad', 'siparis_no', 'fatura_no', 'magaza'],
            'konu' => 'Faturanız — {siparis_no}',
            'baslik' => 'Faturanız hazır',
            'metin' => 'Merhaba {ad}, {siparis_no} numaralı siparişinizin faturası ({fatura_no}) ektedir. Sipariş sayfanızdan da istediğiniz zaman indirebilirsiniz.',
            'not' => '',
        ],
        'iade-onay' => [
            'ad' => 'İade onaylandı',
            'degiskenler' => ['ad', 'talep_no', 'siparis_no', 'tutar', 'magaza'],
            'konu' => 'İade talebiniz onaylandı — {talep_no}',
            'baslik' => 'İade talebiniz onaylandı',
            'metin' => 'Ürününüz tarafımıza ulaştı ve incelendi. İade tutarı {tutar} TL olarak onaylandı; ödemeyi yaptığınız karta 14 gün içinde iade edilecek. Bankaya göre hesabınıza yansıması birkaç gün sürebilir.',
            'not' => '',
        ],
        'degisim-onay' => [
            'ad' => 'Değişim onaylandı',
            'degiskenler' => ['ad', 'talep_no', 'siparis_no', 'magaza'],
            'konu' => 'Değişim talebiniz onaylandı — {talep_no}',
            'baslik' => 'Değişim talebiniz onaylandı',
            'metin' => 'Ürününüz tarafımıza ulaştı. Talep ettiğiniz beden hazırlanıyor; kargoya verildiğinde takip numarasını ileteceğiz. Değişimde ücret iadesi yapılmaz.',
            'not' => '',
        ],
        'iade-ret' => [
            'ad' => 'İade / değişim reddedildi',
            'degiskenler' => ['ad', 'talep_no', 'siparis_no', 'magaza'],
            'konu' => 'İade talebiniz hakkında — {talep_no}',
            'baslik' => 'İade talebiniz hakkında',
            'metin' => 'Talebinizi inceledik ancak onaylayamadık. Gerekçe aşağıda. Sorunuz olursa bize yazabilirsiniz.',
            'not' => '',
        ],
        'iade-tamam' => [
            'ad' => 'İade tutarı gönderildi',
            'degiskenler' => ['ad', 'talep_no', 'siparis_no', 'tutar', 'magaza'],
            'konu' => 'İade tutarınız gönderildi — {talep_no}',
            'baslik' => 'İade tutarınız gönderildi',
            'metin' => 'İade tutarı {tutar} TL ödemeyi yaptığınız karta gönderildi. Bankaya göre hesabınıza yansıması birkaç gün sürebilir.',
            'not' => '',
        ],
        'degisim-tamam' => [
            'ad' => 'Değişim ürünü kargoda',
            'degiskenler' => ['ad', 'talep_no', 'siparis_no', 'kargo_firma', 'takip_no', 'magaza'],
            'konu' => 'Değişim ürününüz yola çıktı — {talep_no}',
            'baslik' => 'Değişim ürününüz yola çıktı',
            'metin' => 'Değişim ürününüz kargoya verildi. Takip numarası: {takip_no} ({kargo_firma}).',
            'not' => '',
        ],
    ];

    public const DEGISKEN_ACIKLAMALARI = [
        'ad' => 'Müşterinin adı soyadı',
        'siparis_no' => 'Sipariş numarası',
        'magaza' => 'Mağaza adı',
        'cayma_gun' => 'İade hakkı gün sayısı',
        'kargo_firma' => 'Kargo firması',
        'takip_no' => 'Kargo takip numarası',
        'urun' => 'Ürün adı ve varyantı',
        'dakika' => 'Bağlantının geçerlilik süresi',
        'talep_no' => 'İade talep numarası',
        'tutar' => 'İade tutarı (ör. 2.890,00)',
        'fatura_no' => 'Fatura numarası',
    ];

    /**
     * @param  array<string, scalar|null>  $degerler
     * @return array{konu: string, baslik: string, metin: string, not: string}
     */
    public static function al(string $anahtar, array $degerler = []): array
    {
        $varsayilan = self::SABLONLAR[$anahtar];
        $kayit = null;

        try {
            $kayit = EpostaSablonu::where('anahtar', $anahtar)->first();
        } catch (Throwable) {
            // Tablo yoksa (ilk migrate) varsayılanla devam
        }

        $degerler += ['magaza' => config('shop.ad')];
        $yer = [];
        foreach ($degerler as $ad => $deger) {
            $yer['{'.$ad.'}'] = (string) $deger;
        }

        $sonuc = [];
        foreach (['konu', 'baslik', 'metin', 'not'] as $alan) {
            $metin = filled($kayit?->{$alan}) ? $kayit->{$alan} : $varsayilan[$alan];

            // Panelde "-" yazılan alan tamamen gizlenir (ör. alttaki notu kaldırmak için)
            if (trim($metin) === '-') {
                $metin = '';
            }

            $sonuc[$alan] = strtr($metin, $yer);
        }

        return $sonuc;
    }
}
