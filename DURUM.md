# Zeys Fashion House — Durum

Son güncelleme: 2026-09-21 · 126 test, 561 iddia (1 atlanan)

> **Proje canlıya çıkmaya hazır DEĞİL.** Aşağıda ne bitti, ne bitmedi
> ve neyin beklediği yazıyor.

---

## Ne çalışıyor

### Motor

| Parça | Durum |
|---|---|
| Beden × Renk varyant matrisi | ✅ Kombinasyon üretimi, sönük kalan seçenekler, tam eşleşme |
| Sepet | ✅ Oturum tabanlı, fiyat daima sunucudan |
| İki aşamalı stok | ✅ `none → reserved → committed`, tekrarlanan çağrıya dayanıklı |
| Sipariş | ✅ Kalemler varyant kimliğiyle bağlı, adres/ad/SKU anlık kopya |
| Kupon | ✅ Yüzde/tutar, eşik, kota, süre |
| PayTR | ⚠️ Kod ve testler hazır, **gerçek anahtarla hiç denenmedi** |
| İade / değişim | ✅ Durum makinesi, kısmi iade, cayma hakkı süresi |
| Kargo | ✅ Tek ücret + ücretsiz eşiği, takip no |
| Yasal metinler | ⚠️ Sürümlü altyapı hazır, **metinler hukukçu onayından geçmedi** |

### Vitrin

Ana sayfa · koleksiyon listesi · koleksiyon detayı · ürün sayfası
(beden/renk seçici, beden tablosu, renk galerisi) · sepet · kasa ·
ödeme dönüşü · yasal sayfalar · iletişim.

Toplam yük: **18,3 kB CSS + 0,43 kB genel JS + 2,6 kB ürün JS**.

### Panel

| Ekran | Durum |
|---|---|
| Ürünler | ✅ Sekmeli form, eksen tanımı, kombinasyon üretimi, varyant matrisi, rezerv onarma |
| Siparişler | ✅ Kargola / teslim / iptal, takılı rezerv süzgeci, salt okunur tutarlar |
| İade / Değişim | ✅ Durum makinesi eylemleri, kalem listesi, menüde açık talep rozeti |
| Beden Tabloları | ✅ Satır bazlı düzenleyici, tutarlılık uyarısı |
| Kuponlar | ✅ Gerçek kullanılabilirlik durumu |
| Kategoriler | ✅ Beden tablosu mirası görünür |
| Koleksiyonlar | ✅ Vitrindeki ürün sayısı ayrı gösterilir |
| Yasal Metinler | ✅ Yeni sürüm yayımlama, bağlı siparişi olan sürüm silinemez |

---

## Ne bitmedi

### Vitrin eksikleri

- [ ] **Ürün fotoğrafları** — kod hazır, tek fotoğraf yüklenmedi.
      Konfeksiyon sitesinin asıl içeriği bu.
- [ ] **Sipariş sorgulama** sayfası (alt bilgide bağlantı var, rota yok)
- [ ] **Müşteri hesabı** ve sipariş geçmişi
- [ ] **Müşterinin iade talebi açacağı arayüz** — servis ve panel kuyruğu
      hazır, müşteri tarafı yok
- [ ] **Sipariş e-postaları** — onay, kargoya verildi, iade sonucu
- [ ] "Stokta yok — haber ver"
- [ ] Arama, kategori sayfaları, filtreleme
- [ ] SEO: sitemap, JSON-LD, robots.txt

### Bilinen kırılganlık

- **Beden tablosu düğmesi** yalnızca eksen adı tam olarak "Beden"
  olduğunda çıkıyor (`urun.blade.php`). Mağaza ekseni "Ölçü" diye
  adlandırırsa tablo görünmez.
- `DEPLOY.md` yazılmadı. `.cpanel.yml` ve `.env.production.example`
  hazır ve teste bağlı, ama **hiçbir sunucuda çalıştırılmadı**.

### Kullanıcıdan bekleyenler

1. **GitHub deposu onayı** — SSH'sız cPanel klonlaması yüzünden depo
   pratikte public olmak zorunda; `.env` asla commit'lenmemeli.
2. **PayTR** mağaza no / anahtar / salt
3. **Firma unvanı, telefon, MERSİS, vergi dairesi/no** — yasal
   metinlerde şu an `[GİRİLMEDİ]` yer tutucuları duruyor
4. **Alan adı**
5. **cPanel erişimi**: kök dizin değiştirilebiliyor mu, hangi PHP sürümü
6. **Ürün fotoğrafları**

---

## Yerel çalıştırma

```bash
npm run start
```

`http://127.0.0.1:8141` · panel `/admin`

Yönetici oluşturma:

```bash
php artisan zeys:yonetici
```

Demo veri (canlıda **asla**):

```bash
php artisan db:seed --class=DemoSeeder
php artisan db:seed --class=LegalDocumentSeeder
```

---

## Yol boyu yakalanan gerçek hatalar

Bunlar sessiz hatalardı; not düşülüyor ki tekrar edilmesin.

1. **Panel canlıda hiç açılmayacaktı.** Filament 5'te `User` modeli
   `FilamentUser` uygulamıyorsa erişim yalnızca `local` ortamda açık.
   Test 403 verince görüldü → `users.role` + `canAccessPanel`.
2. **Yarım rezervasyonu elle telafi etmek başkasının rezervini
   çalıyordu.** İşlem geri sarmaya çevrildi.
3. **Tükenen sepet satırı sessizce siliniyordu** — müşteri aldığını
   sanırken sipariş onsuz geçiyordu.
4. **PayTR tutar uyuşmazlığında donör kod siparişi yine de "ödendi"
   yapıyordu** — eksik tahsilatla kargo çıkması demek.
5. **Bir test gerçekten paytr.com'a istek atıyordu** (logda görüldü).
6. **Filament süzgeç kapanışının parametresi `$query` olmalı**; `$sorgu`
   yazınca `null` gelip sayfa 500 veriyordu. Üç süzgeçte vardı, ikisi
   kullanıcı tıklayınca patlayacaktı.
7. **İade ekranı `status`'u serbest metin olarak yazdırıyordu** —
   durum makinesi atlanıyor, stok geri gelmiyordu.
8. **Beden tablosu JSON alanları düz Textarea'daydı**; yönetici yazınca
   veri bozulurdu. İç içe tekrarlayıcı denendi, o da satırları
   boşaltıyordu (test yakaladı) → satır bazlı düzenleyiciye geçildi.
9. **Altın metin için kullanılamıyor**: logonun `#BC9C51` rengi beyaz
   üstünde 2,62:1. Dekor ve metin tonları ayrıldı.
10. **Blade metinlerini ASCII yazmak** `lang=tr` + `uppercase` ile
    "KOLEKSİYONLARİ" üretiyordu.
