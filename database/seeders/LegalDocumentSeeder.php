<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Yasal metin ŞABLONLARI.
 *
 * ⚠️ Bunlar hukukçu onayından geçmiş metinler değildir. 6502 sayılı
 * Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler
 * Yönetmeliği'nin istediği başlıkları taşıyan taslaklardır; yayına
 * çıkmadan önce bir hukukçuya okutulmalıdır.
 *
 * {{satici_*}} yer tutucuları config/shop.php üzerinden doldurulur;
 * .env'deki SHOP_SATICI_* alanları boşken metinler eksik kalır.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $slug => $doc) {
            if (LegalDocument::current($slug)) {
                continue; // panelden düzenlenmiş olabilir, üzerine yazma
            }

            LegalDocument::publish($slug, $doc['title'], $this->fill($doc['body']));
        }
    }

    private function fill(string $body): string
    {
        return strtr($body, [
            '{{magaza}}' => config('shop.ad'),
            '{{satici_unvan}}' => config('shop.satici.unvan') ?: '[SATICI UNVANI GİRİLMEDİ]',
            '{{satici_adres}}' => config('shop.satici.adres') ?: '[ADRES GİRİLMEDİ]',
            '{{satici_telefon}}' => config('shop.satici.telefon') ?: '[TELEFON GİRİLMEDİ]',
            '{{satici_eposta}}' => config('shop.satici.eposta') ?: '[E-POSTA GİRİLMEDİ]',
            '{{satici_mersis}}' => config('shop.satici.mersis') ?: '[MERSİS NO GİRİLMEDİ]',
            '{{cayma_gun}}' => (string) config('shop.cayma_hakki_gun'),
            '{{kargo_esik}}' => number_format((float) config('shop.kargo.ucretsiz_esigi'), 2, ',', '.'),
        ]);
    }

    /** @return array<string, array{title:string, body:string}> */
    private function documents(): array
    {
        return [
            'on-bilgilendirme' => [
                'title' => 'Ön Bilgilendirme Formu',
                'body' => <<<'HTML'
<h2>1. Satıcı Bilgileri</h2>
<p>
    Unvan: {{satici_unvan}}<br>
    Adres: {{satici_adres}}<br>
    Telefon: {{satici_telefon}}<br>
    E-posta: {{satici_eposta}}<br>
    MERSİS No: {{satici_mersis}}
</p>

<h2>2. Sözleşme Konusu Ürün</h2>
<p>
    Sözleşmenin konusu, ALICI'nın {{magaza}} internet sitesi üzerinden
    elektronik ortamda siparişini verdiği, nitelikleri ve satış fiyatı
    sipariş özetinde belirtilen ürünlerin satışı ve teslimidir.
</p>

<h2>3. Ödeme ve Teslimat</h2>
<p>
    Ürün bedeli, sipariş sırasında seçilen ödeme yöntemiyle tahsil edilir.
    Kart bilgileri ödeme kuruluşunun güvenli sayfasında girilir ve
    {{magaza}} tarafından saklanmaz.
</p>
<p>
    Kargo bedeli sipariş özetinde ayrıca gösterilir.
    {{kargo_esik}} TL ve üzeri siparişlerde kargo ücretsizdir.
</p>

<h2>4. Cayma Hakkı</h2>
<p>
    ALICI, malı teslim aldığı tarihten itibaren {{cayma_gun}} gün içinde
    hiçbir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden
    cayma hakkına sahiptir. Cayma hakkının kullanıldığına dair bildirim
    bu süre içinde SATICI'ya yöneltilmelidir.
</p>

<h2>5. Cayma Hakkının Kullanılamayacağı Durumlar</h2>
<p>
    Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesi uyarınca; ALICI'nın
    isteği doğrultusunda kişiye özel hazırlanan ürünlerde, tesliminden
    sonra ambalajı açılmış olan ve iadesi sağlık/hijyen açısından uygun
    olmayan ürünlerde (iç giyim, çorap, mayo vb.) cayma hakkı
    kullanılamaz.
</p>

<h2>6. Uyuşmazlık</h2>
<p>
    Uyuşmazlıklarda, Ticaret Bakanlığı'nca ilan edilen parasal sınırlar
    dâhilinde ALICI'nın yerleşim yerindeki Tüketici Hakem Heyetleri ve
    Tüketici Mahkemeleri yetkilidir.
</p>
HTML,
            ],

            'mesafeli-satis' => [
                'title' => 'Mesafeli Satış Sözleşmesi',
                'body' => <<<'HTML'
<h2>Madde 1 — Taraflar</h2>
<p>
    SATICI: {{satici_unvan}}, {{satici_adres}},
    {{satici_telefon}}, {{satici_eposta}}, MERSİS: {{satici_mersis}}.<br>
    ALICI: Sipariş formunda bilgileri yer alan kişi.
</p>

<h2>Madde 2 — Konu</h2>
<p>
    İşbu sözleşmenin konusu, ALICI'nın {{magaza}} internet sitesinden
    elektronik ortamda sipariş verdiği ürünün satışı ve teslimi ile ilgili
    olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli
    Sözleşmeler Yönetmeliği hükümleri uyarınca tarafların hak ve
    yükümlülüklerinin belirlenmesidir.
</p>

<h2>Madde 3 — Sözleşme Konusu Ürün</h2>
<p>
    Ürünün türü, miktarı, marka/modeli, rengi, bedeni, adedi, satış
    bedeli ve ödeme şekli sipariş özetinde gösterildiği gibidir ve işbu
    sözleşmenin ayrılmaz parçasıdır.
</p>

<h2>Madde 4 — Genel Hükümler</h2>
<p>
    ALICI, sözleşme konusu ürünün temel nitelikleri, satış fiyatı ve
    ödeme şekli ile teslimata ilişkin ön bilgileri okuyup bilgi sahibi
    olduğunu ve elektronik ortamda gerekli teyidi verdiğini kabul eder.
</p>
<p>
    Sözleşme konusu ürün, yasal 30 günlük süreyi aşmamak koşuluyla
    ALICI'nın belirttiği adrese kargo firması aracılığıyla teslim edilir.
</p>

<h2>Madde 5 — Cayma Hakkı</h2>
<p>
    ALICI, malı teslim aldığı tarihten itibaren {{cayma_gun}} gün içinde
    cayma hakkını kullanabilir. Cayma hakkının kullanılması hâlinde ürün,
    faturası ve varsa standart aksesuarlarıyla birlikte eksiksiz ve
    hasarsız olarak iade edilir. Bedel, cayma bildiriminin SATICI'ya
    ulaşmasından itibaren 14 gün içinde ALICI'ya iade edilir.
</p>

<h2>Madde 6 — Değişim</h2>
<p>
    Beden ya da renk değişimi taleplerinde ürün, kullanılmamış ve
    etiketleri sökülmemiş olmak kaydıyla değiştirilir. Değişimde bedel
    iadesi yapılmaz; talep edilen yeni ürün gönderilir.
</p>

<h2>Madde 7 — Yetkili Mahkeme</h2>
<p>
    İşbu sözleşmeden doğan uyuşmazlıklarda Tüketici Hakem Heyetleri ve
    Tüketici Mahkemeleri yetkilidir.
</p>
HTML,
            ],

            'iade-degisim' => [
                'title' => 'İade ve Değişim Koşulları',
                'body' => <<<'HTML'
<h2>İade</h2>
<p>
    Ürünü teslim aldığınız tarihten itibaren {{cayma_gun}} gün içinde
    iade talebi oluşturabilirsiniz. Ürünün kullanılmamış, yıkanmamış ve
    etiketlerinin sökülmemiş olması gerekir.
</p>

<h2>Değişim</h2>
<p>
    Beden tutmadığında ürünü aynı modelin başka bedeniyle
    değiştirebilirsiniz. Değişimde para iadesi yapılmaz; stokta varsa
    talep ettiğiniz beden gönderilir, yoksa iade sürecine dönülür.
</p>

<h2>Süreç</h2>
<ol>
    <li>Hesabınızdaki sipariş sayfasından talep açarsınız.</li>
    <li>Size kargo bilgisi iletilir, ürünü geri gönderirsiniz.</li>
    <li>Ürün tarafımıza ulaşır ve incelenir.</li>
    <li>Talep onaylanır; iadede bedel 14 gün içinde iade edilir.</li>
</ol>

<h2>İade Edilemeyen Ürünler</h2>
<p>
    Hijyen gerekçesiyle iç giyim, çorap ve mayo grubu ürünler,
    ambalajı açılmış olmak kaydıyla iade alınamaz.
</p>
HTML,
            ],

            'kvkk' => [
                'title' => 'KVKK Aydınlatma Metni',
                'body' => <<<'HTML'
<h2>Veri Sorumlusu</h2>
<p>
    6698 sayılı Kişisel Verilerin Korunması Kanunu uyarınca veri
    sorumlusu {{satici_unvan}}'dır.
</p>

<h2>İşlenen Veriler ve Amaç</h2>
<p>
    Ad-soyad, iletişim bilgileri, teslimat ve fatura adresi ile sipariş
    geçmişi; siparişin oluşturulması, ödemenin alınması, kargo teslimatı,
    fatura düzenlenmesi, iade ve değişim süreçlerinin yürütülmesi ve
    yasal saklama yükümlülüklerinin yerine getirilmesi amacıyla işlenir.
</p>

<h2>Aktarım</h2>
<p>
    Veriler; ödeme kuruluşu, kargo firması ve yasal olarak yetkili kamu
    kurumlarıyla, yalnızca ilgili amaçla sınırlı olarak paylaşılır.
    Kart bilgileri tarafımızca görülmez ve saklanmaz.
</p>

<h2>Haklarınız</h2>
<p>
    KVKK'nın 11. maddesi kapsamındaki haklarınız için
    {{satici_eposta}} adresine başvurabilirsiniz.
</p>
HTML,
            ],

            'cerez' => [
                'title' => 'Çerez Politikası',
                'body' => <<<'HTML'
<h2>Kullandığımız Çerezler</h2>
<p>
    {{magaza}} yalnızca sitenin çalışması için zorunlu çerezleri kullanır:
    oturum çerezi (sepetinizin ve girişinizin korunması) ve güvenlik
    çerezi (form güvenliği).
</p>

<h2>Reklam ve İzleme</h2>
<p>
    Üçüncü taraf reklam veya profilleme çerezi kullanılmamaktadır.
    İleride eklenmesi hâlinde bu metin güncellenecek ve onayınız
    alınacaktır.
</p>

<h2>Çerezleri Kapatmak</h2>
<p>
    Tarayıcı ayarlarından çerezleri engelleyebilirsiniz; ancak zorunlu
    çerezler kapatıldığında sepet ve giriş işlevleri çalışmaz.
</p>
HTML,
            ],
        ];
    }
}
