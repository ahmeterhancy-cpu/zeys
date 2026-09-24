# Canlıya almadan önce — toplanacak bilgiler

Bu dosya **doldurulmak için** var. Boşlukları doldurun; deploy sırasında
her değer buradan alınacak. Tarif: [DEPLOY.md](DEPLOY.md).

> Buraya **parola yazmayın.** Depo geçmişine giren parola yanmış sayılır
> ve değiştirilmesi gerekir. Parolalar yalnızca sunucudaki `.env`
> dosyasına ve cPanel ekranlarına girilir. Bu dosyada parolanın
> **nerede olduğu** yazar, kendisi değil.

---

## 1. Sunucu (cPanel'e bakarak doldurun)

| Bilgi | Nerede bakılır | Değer |
|---|---|---|
| cPanel kullanıcı adı | cPanel ana ekranı → "Kullanıcı Adı" | `________` |
| Alan adı | Yayına çıkacak adres (https ile) | `________` |
| Sunucu / paket | Turhost mu, başka mı | `________` |
| **SSH var mı?** | Terminal ya da SSH Access simgesi | ☐ var ☐ yok |
| **Composer var mı?** | SSH'de `composer -V` | ☐ var ☐ yok ☐ SSH yok, bilinmiyor |
| **PHP sürümü** | MultiPHP Manager **ve** PHP Selector (ikisi ayrı) | `____` / `____` |
| **Kök dizin değiştirilebiliyor mu?** | Domains → alan adının kök dizini | ☐ evet ☐ hayır |
| Node / npm var mı? | Neredeyse hiçbir paylaşımlı pakette yok | ☐ var ☐ yok |

Bu satırların **neyi değiştirdiği**:

- **SSH yok** → deploy key üretilemez, depo pratikte herkese açık olmak
  zorunda; `artisan` işleri `.cpanel.yml` görevlerinden yürür.
- **Composer yok** → `vendor/` klasörü depoya eklenir (~100 MB),
  DEPLOY.md 3.5.
- **PHP sürümü** → `composer.json` içindeki `config.platform.php`
  sunucununkine eşit ya da ondan küçük olmalı. Şu an `8.3.33`.
  Sunucu 8.2 ise indirilecek. Yanlışsa site **beyaz ekran** verir
  (DEPLOY.md tuzak E).
- **Kök dizin değiştirilebiliyor** → temiz kurulum, `.env` web'e hiç
  açılmaz (DEPLOY.md 1). Değiştirilemiyorsa `public_html/zeys_app`
  düzeni (DEPLOY.md 2).
- **Node yok** → `public/build` yerelde derlenip depoya konur (yapıldı).

---

## 2. Depo

cPanel → Git Version Control bir **klon adresi** ister.

| Bilgi | Değer |
|---|---|
| Depo nerede duracak | ☐ GitHub (özel) ☐ GitHub (herkese açık) ☐ cPanel'de |
| Klon adresi | `________` |
| Deploy key eklendi mi (özel depo + SSH) | ☐ evet ☐ gerekmedi |

> Şu an yerel depoda **uzak sunucu tanımlı değil**. GitHub'da depo
> açılması ayrıca onayınızı gerektiriyor; söylemeden açmıyorum.

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
| Callback adresi PayTR panelinde tanımlandı mı | ☐ evet → `https://ALANADI/paytr/callback` |

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
- [ ] **`https://ALANADI/zeys_app/.env` → 403 dönüyor mu?** 200 dönerse
      hemen durun, bütün parolaları değiştirin.

---

## 9. Ben (Claude) neye bakmadan ilerleyemiyorum

Sırasıyla en kritik olanlar:

1. cPanel kullanıcı adı → `.cpanel.yml` içindeki üç yol satırı onsuz
   doldurulamaz (şu an `KULLANICI` yazıyor).
2. Kök dizin değiştirilebiliyor mu → kurulumun şeklini belirliyor.
3. SSH ve Composer var mı → `vendor/` depoya girecek mi.
4. PHP sürümü → `composer.json` platform kilidi.
5. Depo nerede duracak → GitHub'da depo açılması onayınıza bağlı.

Bunlar gelince: yolları doldurur, gerekiyorsa `vendor/`'ı depoya ekler,
`.env` için hazır bir taslak çıkarır ve adım adım hangi düğmeye
basacağınızı yazarım.
