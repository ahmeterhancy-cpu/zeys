# Canlıya almadan önce — toplanacak bilgiler

Bu dosya **doldurulmak için** var. Boşlukları doldurun; deploy sırasında
her değer buradan alınacak. Tarif: [DEPLOY.md](DEPLOY.md).

> Buraya **parola yazmayın.** Depo geçmişine giren parola yanmış sayılır
> ve değiştirilmesi gerekir. Parolalar yalnızca sunucudaki `.env`
> dosyasına ve cPanel ekranlarına girilir. Bu dosyada parolanın
> **nerede olduğu** yazar, kendisi değil.

---

## 1. Sunucu (cPanel'e bakarak doldurun)

Referans proje **Ay Parçası** (`ayparcasicicekci.com`) aynı yöntemle
canlıda; oradaki kısıtlar burada da **varsayılan kabul edildi** ve kod ona
göre hazırlandı. Farklı çıkarsa yalnız ilgili satır değişir.

| Bilgi | Nerede bakılır | Referansta | Bu hesapta |
|---|---|---|---|
| cPanel kullanıcı adı | — | `aypa8479` | **zeys9011** ✅ |
| Alan adı | — | ayparcasicicekci.com | **zeysfashionhouse.com** ✅ |
| **SSH var mı?** | Terminal / SSH Access | **yok** | **yok** (araç listesinde Terminal yok) |
| **Composer var mı?** | SSH'de `composer -V` | **yok** → `vendor/` depoda | ☐ var ☐ yok |
| **PHP sürümü** | PHP Selector | ea-php83 | **8.3** ✅ uyumlu |
| **Kök dizin değiştirilebiliyor mu?** | Domains → kök dizin | **hayır** → `public_html/zeys_app` | ☐ evet ☐ hayır |
| **Sembolik bağ takip ediliyor mu?** | Kurulumdan sonra görseller açılıyor mu | **hayır** → gerçek klasör | ☐ evet ☐ hayır |
| Node / npm var mı? | Paylaşımlı pakette yok | yok → `public/build` depoda | ☐ var ☐ yok |

Referans kısıtlara göre **zaten yapılanlar**:

- `public/build` depoya konuldu (sunucuda Node yok).
- `vendor/` depoya konuldu (sunucuda Composer yok); `composer install`
  yine de varsa `.cpanel.yml` onu çalıştırıyor.
- `public_html/zeys_app` düzeni ve klasörü web'e kapatan `.htaccess`
  hazır (`deploy/cpanel/`).
- Sembolik bağ takip edilmezse diye `FILESYSTEM_PUBLIC_ROOT` desteği
  eklendi: değeri `public_html/storage` gösterirse dosyalar doğrudan
  oraya yazılır.
- `composer.json` PHP **8.3.33**'e kilitli — sunucu 8.2 ise indirilecek,
  8.4 ise dokunulmayacak. Yanlışsa site **beyaz ekran** verir.

**Hesap bilgileri (2026-09-25, panelden okundu):**

| | |
|---|---|
| Sunucu | `srvc197.trwww.com` (Turhost), IP 94.199.205.198 |
| cPanel adresi | `https://srvc197.trwww.com:2083` — alan adı üzerinden girmeyin, sertifika uyuşmuyor |
| Kullanıcı | `zeys9011` |
| Ana dizin | **`/home2/zeys9011`** — `/home` değil, `.cpanel.yml` buna göre dolduruldu |
| PHP | 8.3 (PHP Selector) |
| SSL | **self-signed** — AutoSSL çalıştırılmalı, yoksa tarayıcı "güvenli değil" der |

---

## 2. Depo

cPanel → Git Version Control bir **klon adresi** ister ve adres içinde
parola kabul etmez; SSH olmadığı için deploy key de üretilemez. Referans
projede bu yüzden depo **herkese açık**:
`https://github.com/ahmeterhancy-cpu/ayparcasi`

| Bilgi | Değer |
|---|---|
| Depo | `https://github.com/ahmeterhancy-cpu/zeys` ✅ açıldı, push edildi |
| Klon adresi | `https://github.com/ahmeterhancy-cpu/zeys.git` |
| Varsayılan dal | `main` ✅ |
| Görünürlük | **şu an ÖZEL** — cPanel klonlayabilmesi için herkese açık olmalı |

> **Sıradaki iş:** GitHub → depo → Settings → General → en altta
> *Change repository visibility* → **Public**. cPanel klon adresinde
> parola kabul etmiyor, SSH yoksa deploy key de üretilemiyor; özel depo
> klonlanamaz.
>
> Depo açılmadan önce doğrulandı: git geçmişinde **hiç `.env` yok**,
> kodda ve geçmişte parola/anahtar yok. Bu böyle kalmalı — bir kez
> commit'lenen parola yanmış sayılır.

---

## 3. Veritabanı (cPanel → MySQL Databases)

| Bilgi | Değer |
|---|---|
| Veritabanı adı | `________` |
| Kullanıcı adı | `________` |
| Parola | cPanel'de üretildi, `.env` içine yazılacak — **buraya yazmayın** |
| Kullanıcıya ALL PRIVILEGES verildi mi | ☐ evet |

`DB_HOST` daima `localhost` (DEPLOY.md tuzak C).

---

## 4. E-posta (sipariş onayı, fatura, şifre sıfırlama)

| Bilgi | Değer |
|---|---|
| Gönderen adresi (cPanel → Email Accounts) | `________` |
| SMTP sunucusu | `________` |
| Port / şifreleme | ☐ 465 SSL ☐ 587 TLS |
| Parola | yalnız `.env` içine |
| Yeni sipariş bildirimi hangi adrese gitsin | `________` |

---

## 5. Firma bilgileri (yasal metinler ve faturalar için)

Boş kalırsa vitrindeki yasal metinlerde **`[GİRİLMEDİ]`** görünür.
6502 sayılı kanun bu bilgileri zorunlu tutuyor.

| Bilgi | Değer |
|---|---|
| Satıcı ünvanı (tam yasal ad) | `________` |
| Adres | `________` |
| Telefon | `________` |
| E-posta | `________` |
| MERSİS numarası | `________` |
| Vergi dairesi / numarası | `________` |
| ETBİS kaydı yapıldı mı | ☐ evet ☐ hayır |
| Ticaret sicil numarası | `________` |
| İade adresi (farklıysa) | `________` |
| Kargo firması ve teslim süresi | `________` |

---

## 6. PayTR

| Bilgi | Değer |
|---|---|
| Mağaza no (`PAYTR_MERCHANT_ID`) | `________` |
| Anahtar / tuz | yalnız `.env` içine |
| Test modu ile mi başlıyoruz | ☐ evet (`PAYTR_TEST_MODE=1`) |
| Callback adresi PayTR panelinde tanımlandı mı | ☐ evet → `https://zeysfashionhouse.com/paytr/callback` |

Callback tanımlanmazsa ödeme alınır ama sipariş **"ödendi" olmaz**.

---

## 7. İçerik

| Bilgi | Durum |
|---|---|
| Gerçek ürün fotoğrafları | ☐ hazır ☐ bekleniyor (şu an demo görseller) |
| Ürün listesi (ad, fiyat, beden/renk, stok) | ☐ hazır ☐ bekleniyor |
| Logo (varyantlarıyla) | ☐ hazır |
| Ana sayfa slayt görselleri | ☐ hazır ☐ otomatik içerik kullanılacak |
| Hakkımızda / iletişim metinleri | ☐ hazır |
| Yasal metinler hukukçuya okutuldu mu | ☐ evet ☐ hayır |

---

## 8. Yayın sonrası zorunlu iki iş

- [ ] **Cron** (cPanel → Cron Jobs, her dakika) — yoksa takılı rezervler
      temizlenmez, stok vitrinde "tükendi" görünür ama rafta durur.
      Satır DEPLOY.md 3.11'de.
- [ ] **`https://zeysfashionhouse.com/zeys_app/.env` → 403 dönüyor mu?** 200 dönerse
      hemen durun, bütün parolaları değiştirin.

---

## 9. Sırada ne var

Kod tarafı hazır: `public/build` ve `vendor/` depoda, `public_html/zeys_app`
düzeni ve `.htaccess` koruması yerinde, sembolik bağ tutmazsa diye çıkış
yolu eklendi.

Bekleyen iki şey:

1. **cPanel kullanıcı adı ve alan adı** → `.cpanel.yml` içindeki üç yol
   satırı doldurulacak (şu an `KULLANICI` yazıyor).
2. **GitHub deposu onayı** → depo açılıp kod push'lanacak, cPanel oradan
   klonlayacak.

Bunlar gelince sıra: depo aç → push → cPanel Git Version Control → Create →
Deploy HEAD Commit → `.env` oluştur → tekrar deploy → yönetici hesabı →
yasal metinler → cron → PayTR callback. Ayrıntısı [DEPLOY.md](DEPLOY.md).
