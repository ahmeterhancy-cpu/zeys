# Zeys Fashion House — Durum

Son güncelleme: 2026-09-21 · **316 test** (1 atlanan, kasıtlı)

> **Özellikler tamam. Canlıya çıkmak için senden bilgi bekleniyor** —
> aşağıdaki "Bekleyenler" bölümü. Kod hiçbir sunucuda çalıştırılmadı.

---

## Ne çalışıyor

### Motor

| Parça | Durum |
|---|---|
| Beden × Renk varyant matrisi | ✅ Kombinasyon üretimi, sönük seçenekler, tam eşleşme |
| Sepet | ✅ Oturum tabanlı, fiyat daima sunucudan |
| İki aşamalı stok | ✅ `none → reserved → committed`, tekrara dayanıklı |
| Sipariş | ✅ Kalemler varyant kimliğiyle, ad/SKU/adres anlık kopya |
| Fatura bilgisi | ✅ Bireysel (TCKN doğrulamalı) / kurumsal (VKN), farklı fatura adresi |
| Kupon | ✅ Yüzde/tutar, eşik, kota, süre |
| PayTR ödeme | ⚠️ Kod ve testler hazır, **gerçek anahtarla denenmedi** |
| PayTR iade (API) | ⚠️ Panelden tam/kısmi iade, çift iade kilidi — **gerçek anahtarla denenmedi** |
| İade / değişim | ✅ Durum makinesi, kısmi iade, cayma hakkı |
| Kargo | ✅ Tek ücret + ücretsiz eşiği, takip no |
| E-posta | ✅ Sipariş onayı (+ mağaza kopyası), sözleşme belgeleri, kargo, iade sonucu, stokta, parola sıfırlama, düşük stok |
| Stokta yok → haber ver | ✅ Varyant bazında |
| Müşteri hesabı | ✅ Kayıt, giriş, **parola sıfırlama**, sipariş geçmişi, adres defteri |
| KVKK | ✅ Verilerimi indir (JSON), hesabımı sil (siparişler yasal saklama için ayrılır) |
| Ürün yorumları | ✅ Yalnız teslim edilmiş siparişten, onaydan sonra yayında |
| Bakım perdesi | ✅ Panelden aç/kapa; yönetici siteyi görür, PayTR etkilenmez |
| Yasal metinler | ⚠️ Sürümlü altyapı hazır, **hukukçu onayından geçmedi** |

### Tasarım

Vitrin **PressMart (presslayouts.com, home-2)** düzenine göre baştan giydirildi:
üst şerit, arama çubuklu başlık (kategori seçimli), renkli menü bandı +
"Kategoriler" açılır menüsü, slayt, kategori daireleri, koleksiyon afişleri,
sekmeli ürünler, özellik şeridi, küçük listeler, açık gri alt bilgi, mobilde
sabit alt çubuk ve yan açılır menü. Mağaza sayfaları sol yan sütunlu (kategori,
koleksiyon, fiyat süzgeci) + sıralama çubuklu; ürün sayfası dikey küçük
görselli galeri + Açıklama/Ek Bilgi/Değerlendirmeler sekmeleri + benzer ürünler;
hesap sayfaları sol menülü. Yazı Poppins.

**Renk bilerek referanstan farklı**: referansın yeşili yerine logodan ölçülen
koyu altın (`--vurgu: #7d6840`, beyaz yazıyla 5.4:1). Tek jeton —
`resources/css/base.css`. Yeşil istenirse orası değişir.

**Referanstan bilerek alınmayanlar**: geri sayımlı "günün fırsatı" (gerçek süre
sınırı yok → yanıltıcı olur), istek listesi / karşılaştırma / döviz-dil seçici
(altyapısı yok), bülten formu (altyapısı yok; yerine Instagram bandı). Ana
sayfa bölümleri yalnız veri varsa çizilir: indirim yoksa "Fırsat Ürünleri",
satış yoksa "Çok Satanlar" sekmesi görünmez.

### Vitrin

Ana sayfa · koleksiyonlar · koleksiyon detayı · kategori sayfaları ·
arama · ürün sayfası (beden/renk seçici, beden tablosu, renk galerisi,
haber ver, **puan + yorumlar**) · sepet · kasa (fatura bilgisi) · ödeme
dönüşü · sipariş sorgulama · müşteri iade talebi · **ürün değerlendirme** ·
hesap sayfaları (verilerim dahil) · yasal sayfalar · iletişim · **mobil menü**.

SEO: sitemap.xml, robots.txt (ortama duyarlı), ürün ve mağaza JSON-LD
(**aggregateRating yalnız gerçek, onaylı yorum varken**), canonical, Açık Grafik.

Toplam yük: sıfır kütüphane, elle yazılmış CSS/JS (Poppins derleme anında kendi sunucumuza alınır).

### Panel

**Pano**: bugünkü sipariş/ciro, kargolanacak, açık iade, stoğu biten,
beden bekleyen müşteri, takılı rezerv — her kart ilgili listeye gider.

Ürünler (matris üretimi, galeri + renk başına fotoğraf, toplu yükleme,
rezerv onarma, **SKU bazında fiyat/stok CSV indir-yükle**) · Siparişler
(kargola/teslim/iptal + PayTR iadesi, fatura bilgisi) · İade kuyruğu (durum
makinesi, PayTR iadesi) · **Satış raporu** (tarih aralığı, net ciro, en çok
satanlar, CSV) · **Yorumlar** (yayımla/reddet) · Stok talepleri · Beden
tabloları · Kuponlar · Kategoriler · Koleksiyonlar · Yasal metinler (sürüm
yayımlama) · Kullanıcılar · Site ayarları (bakım perdesi, kargo, bildirim
adresi, düşük stok eşiği, satıcı bilgileri).

**CSV biçimi** Türkçe Excel'e göre: `;` ayraç, ondalık virgül, BOM.
Yükleme hepsi-ya-da-hiçbiri — tek hatalı satırda hiçbir şey uygulanmaz.
Yeni varyant CSV'den açılmaz; stok, ödemesi beklenen adedin altına inemez.

Zamanlanmış iş: `zeys:rezerv-temizle` — yarıda kalan ödemelerin rezervini
15 dakikada bir bırakır. **Sunucuda cron kurulmalı** (DEPLOY.md 3.11).

---

## Bekleyenler — sensiz ilerleyemez

1. **Ürün fotoğrafları.** Panelde galeri ve renk başına toplu yükleme
   hazır. Tek fotoğraf yok; vitrin demo ürünlerle, yer tutucu
   görsellerle duruyor.
2. **GitHub deposu onayı.** SSH'sız cPanel klonlaması yüzünden depo
   pratikte public olmak zorunda; `.env` asla commit'lenmemeli.
3. **PayTR** mağaza no / anahtar / salt (ödeme ve iade aynı anahtarla).
4. **Firma bilgileri**: unvan, telefon, e-posta, MERSİS, vergi
   dairesi/no. Panelde **Site Ayarları**'ndan girilir; sonra "Yasal
   metinleri doldur" düğmesi `[GİRİLMEDİ]` yer tutucularını kapatır.
5. **Alan adı** ve gönderici e-posta (SMTP) bilgileri.
6. **cPanel erişimi**: kök dizin değiştirilebiliyor mu, hangi PHP sürümü.
7. **Hukukçu onayı**: yasal metinler, KVKK saklama süreleri.

---

## Bilinen sınırlar

- **Hiçbir sunucuda çalıştırılmadı.** `DEPLOY.md` referans kurulumdan
  uyarlandı, `.cpanel.yml` kuralları teste bağlı ama ilk deploy gerçek
  sunucuda yapılacak.
- **PayTR'nin gerçek sayfası ve iade uç noktası görülmedi.** İmzalar ve
  callback doğrulaması testli; ilk temas senin anahtarlarınla olacak.
- **E-fatura/e-arşiv entegrasyonu yok.** Kasada fatura bilgisi toplanıyor,
  panelde görünüyor; faturayı muhasebe programında kesmek gerekiyor.
- **Arama sade LIKE.** Ürün sayısı birkaç yüzü geçerse tam metin
  indeksi gerekecek.
- **Kategori ağacı tek seviye** iniyor (kategori + doğrudan altları).
- **E-postalar senkron gönderiliyor** (kuyruk yok). Hata siparişi
  düşürmüyor, yalnız günlüğe yazılıyor; ama yavaş SMTP sayfayı yavaşlatır.
- **Satış raporu KDV'yi ayırmıyor** (tutarlar KDV dahil).

---

## Yerel çalıştırma

```bash
npm run start
```

`http://127.0.0.1:8141` · panel `/admin`

```bash
php artisan zeys:yonetici                          # yönetici oluştur
php artisan db:seed --class=LegalDocumentSeeder    # yasal metin şablonları
php artisan db:seed --class=DemoSeeder             # demo ürünler (canlıda ASLA)
php artisan test
./vendor/bin/pint
npm run build                                      # CSS/JS değişince
```

---

## Yol boyu yakalanan gerçek hatalar

Hepsi sessizdi; not düşülüyor ki tekrar edilmesin.

1. **Panel canlıda hiç açılmayacaktı.** Filament 5'te `User`
   `FilamentUser` uygulamıyorsa erişim yalnızca `local` ortamda açık.
2. **Yarım rezervasyonu elle telafi etmek başkasının rezervini
   çalıyordu.** İşlem geri sarmaya çevrildi.
3. **Tükenen sepet satırı sessizce siliniyordu** — müşteri aldığını
   sanarken sipariş onsuz geçiyordu.
4. **PayTR tutar uyuşmazlığında donör kod siparişi yine de "ödendi"
   yapıyordu** — eksik tahsilatla kargo çıkması demek.
5. **Bir test gerçekten paytr.com'a istek atıyordu** (logda görüldü).
6. **Filament süzgeç kapanışının parametresi `$query` olmalı**; `$sorgu`
   yazınca `null` gelip sayfa 500 veriyordu. Üç süzgeçte vardı.
7. **İade ekranı `status`'u serbest metin yazdırıyordu** — durum
   makinesi atlanıyor, stok geri gelmiyordu.
8. **Beden tablosu JSON alanları düz Textarea'daydı**; yönetici yazınca
   veri bozulurdu. İç içe tekrarlayıcı da satırları boşaltıyordu (test
   yakaladı) → satır bazlı düzenleyici.
9. **"Stokta yok — haber ver" ölü koddu**: stoksuz seçenekler `disabled`
   olduğu için müşteri tükenmiş kombinasyonu hiç seçemiyordu.
10. **Altın metin için kullanılamıyor**: `#BC9C51` beyazda 2,62:1.
11. **Blade metinlerini ASCII yazmak** `lang=tr` + `uppercase` ile
    "KOLEKSİYONLARİ" üretiyordu.
12. **Blade `@context`'i yiyor** — JSON-LD PHP dizisinden üretiliyor.
13. **Beden tablosu düğmesi** eksen adı tam "Beden" olmak zorundaydı.
14. **Panelde galeri yükleme ekranı yoktu** — bu belge "hazır" diyordu.
15. **Ürün sayfası kapak görselini hiç kullanmıyordu**; renk değişimi de
    yalnızca "genel" görsel varsa çalışıyordu.
16. **Takılı rezervleri kimse temizlemiyordu** — PayTR sayfası kapatılınca
    beden sonsuza kadar "tükendi" görünürdü.
17. **Rezervi bırakılmış siparişe geç ödeme gelirse** stok düşmeden
    "ödendi" oluyordu → fazla satış. Artık yeniden rezerve ediliyor, stok
    yoksa sipariş incelemeye düşüyor.
18. **Telefonda menü yoktu** — 860 px altında üst menü gizleniyor, yerine
    bir şey konmuyordu. Ayrıca `1fr` ızgara sütunu 9 px yatay taşma
    yapıyordu; `overflow-x: clip` taşmayı ölçümden gizlediği için
    görünmüyordu → her yerde `minmax(0, 1fr)`.
19. **Panelde sipariş ve iade detay sayfaları 500 veriyordu** — form
    durumundaki tarih metin geliyor, `->format()` patlıyordu.
20. **Hesap silinince hesap geri geliyordu**: `Auth::logout()` "beni
    hatırla" jetonu için kullanıcıyı KAYDEDİYOR; silmeden sonra çağrılınca
    satırı yeniden ekliyordu. Önce çıkış, sonra silme.
21. **Bakım perdesi panelden kapatılamıyordu**: `(string) false === ''`,
    boş değer `.env`'ye düşüyor, `.env`'de açıksa açık kalıyordu.
22. **Ürün stok önbelleği iptal/iade/rezervde tazelenmiyordu, "stokta"
    postası gitmiyordu**: bu yollar `increment()` kullanıyor, Eloquent
    orada `saved` olayını atmıyor, yalnız `updated`. Gözlemci
    `created` + `updated`'a taşındı.
23. **Müşterinin iade formu her gönderimde 403 veriyordu** — rota `signed`
    grubunda, form imzasız `route()` adresine post ediyordu. Testler adresi
    elle imzaladığı için görünmedi; yeni test adresi sayfadaki formdan okuyor.
24. **Ödeme dönüş sayfası ASCII Türkçeydi** ("Siparisiniz alindi") — 11.
    maddedeki hatanın gözden kaçan son örneği; düzeltildi.
