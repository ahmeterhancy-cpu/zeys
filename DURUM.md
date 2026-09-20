# Zeys Fashion House — Durum

Son güncelleme: 2026-09-21 · **199 test, 941 iddia** (1 atlanan)

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
| Kupon | ✅ Yüzde/tutar, eşik, kota, süre |
| PayTR | ⚠️ Kod ve 11 test hazır, **gerçek anahtarla denenmedi** |
| İade / değişim | ✅ Durum makinesi, kısmi iade, cayma hakkı |
| Kargo | ✅ Tek ücret + ücretsiz eşiği, takip no |
| E-posta | ✅ Onay, kargo, iade sonucu, stok bildirimi |
| Stokta yok → haber ver | ✅ Varyant bazında |
| Müşteri hesabı | ✅ Kayıt, giriş, sipariş geçmişi, adres defteri |
| Yasal metinler | ⚠️ Sürümlü altyapı hazır, **hukukçu onayından geçmedi** |

### Vitrin

Ana sayfa · koleksiyonlar · koleksiyon detayı · **kategori sayfaları** ·
**arama** · ürün sayfası (beden/renk seçici, beden tablosu, renk galerisi,
haber ver) · sepet · kasa · ödeme dönüşü · **sipariş sorgulama** ·
**müşteri iade talebi** · **hesap sayfaları** · yasal sayfalar · iletişim.

SEO: sitemap.xml, robots.txt (ortama duyarlı), ürün ve mağaza JSON-LD,
canonical, Açık Grafik.

Toplam yük: **23 kB CSS + 0,43 kB genel JS + 3 kB ürün JS**.

### Panel

Ürünler (matris üretimi, rezerv onarma) · Siparişler (kargola/teslim/
iptal, takılı rezerv süzgeci) · İade kuyruğu (durum makinesi eylemleri) ·
Beden tabloları · Kuponlar · Kategoriler · Koleksiyonlar · Yasal metinler
(sürüm yayımlama).

---

## Bekleyenler — sensiz ilerleyemez

1. **Ürün fotoğrafları.** Galeri, renk başına galeri ve yükleme akışı
   hazır; tek fotoğraf yok. Konfeksiyon sitesinin asıl içeriği bu ve
   vitrin şu an demo ürünlerle, yer tutucu görsellerle duruyor.
2. **GitHub deposu onayı.** SSH'sız cPanel klonlaması yüzünden depo
   pratikte public olmak zorunda; `.env` asla commit'lenmemeli.
3. **PayTR** mağaza no / anahtar / salt.
4. **Firma bilgileri**: unvan, telefon, e-posta, MERSİS, vergi
   dairesi/no. Yasal metinlerde şu an `[GİRİLMEDİ]` yer tutucuları var.
5. **Alan adı.**
6. **cPanel erişimi**: kök dizin değiştirilebiliyor mu, hangi PHP sürümü.

---

## Bilinen sınırlar

- **Hiçbir sunucuda çalıştırılmadı.** `DEPLOY.md` referans kurulumdan
  uyarlandı, `.cpanel.yml` kuralları teste bağlı ama ilk deploy gerçek
  sunucuda yapılacak.
- **PayTR'nin gerçek sayfası görülmedi.** Token imzası ve callback
  doğrulaması testli; ilk temas senin anahtarlarınla olacak.
- **Arama sade LIKE.** Ürün sayısı birkaç yüzü geçerse tam metin
  indeksi gerekecek.
- **Kategori ağacı tek seviye** iniyor (kategori + doğrudan altları).
- **Parola sıfırlama yok.** Müşteri parolasını unutursa şimdilik
  elle yardım gerekiyor.

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
