# Zeys Fashion House — cPanel Deploy

Siteyi Turhost/cPanel üzerinde yayına alma tarifi.
Referans: Ay Parçası (`ayparcasicicekci.com`) aynı yöntemle canlıda.

> ⚠️ **Bu tarif henüz hiçbir sunucuda çalıştırılmadı.** Referans
> kurulumdan uyarlandı ve `.cpanel.yml` kuralları teste bağlandı
> (`php artisan test --filter=DeployYapilandirmaTest`), ama ilk deploy
> gerçek sunucuda yapılacak. Beklenmedik bir şey çıkarsa 6. bölümdeki
> tuzak listesine bakın.

---

## 0. Önce şunları öğrenin

İlk deploy'dan önce cPanel'de bu dört sorunun cevabını bulun. İlk ikisi
kurulumun şeklini değiştiriyor:

| Soru | Nerede bakılır | Neden önemli |
|---|---|---|
| Alan adının **kök dizini değiştirilebiliyor mu?** | Domains → kök dizin | Değiştirilebiliyorsa `index.php` hilesine gerek yok |
| **SSH** var mı? | Terminal / SSH Access | Yoksa `artisan` işleri `.cpanel.yml` görevlerinden yürür |
| **Composer** var mı? | Terminal'de `composer -V` | Yoksa `vendor/` depoda taşınır (~100 MB) |
| Hangi **PHP** sürümü? | MultiPHP Manager **ve** PHP Selector | İkisi ayrı ayrı bakılır, farklı olabilirler |

PHP sürümü kritik: `composer.json` içindeki `config.platform.php`
**sunucunun sürümüne eşit ya da ondan küçük** olmalı. Şu an `8.3.33`'e
kilitli. Sunucu 8.2 ise indirin; 8.4 ise olduğu gibi bırakın.

---

## 1. Kök dizin değiştirilebiliyorsa (tercih edilen)

En temiz kurulum. Uygulamayı `public_html` **dışına** koyun:

```
/home/KULLANICI/zeys/          <- uygulama (web'e kapalı)
/home/KULLANICI/zeys/public/   <- alan adının kök dizini buraya yöneltilir
```

Bu durumda:
- `deploy/cpanel/index.php` **gerekmez**
- `deploy/cpanel/app.htaccess` **gerekmez**
- `.cpanel.yml`'deki 3., 4. ve 5. adımlar çıkarılır, `APPPATH` yukarıdaki
  yola ayarlanır

`.env` web'e hiç açık olmaz. Mümkünse bu yolu seçin.

---

## 2. Kök dizin değiştirilemiyorsa

Ay Parçası'nda durum buydu. Yerleşim:

```
public_html/
├── index.php          <- deploy/cpanel/index.php (bir üst klasörü yükler)
├── .htaccess          <- Laravel'in public/.htaccess dosyası
├── build/             <- Vite çıktısı
├── img/
├── storage            <- sembolik bağ (ya da gerçek klasör, bkz. 6-D)
└── zeys_app/          <- Laravel'in tamamı
    ├── .htaccess      <- ERİŞİMİ KAPATIR, bu dosya hayatidir
    ├── .env           <- elle oluşturulur, depoda YOK
    └── app/ config/ routes/ vendor/ ...
```

> ⚠️ `zeys_app/.htaccess` olmazsa `.env` tarayıcıdan **indirilebilir**.
> Kurulumdan sonra `https://ALANADI/zeys_app/.env` adresini açın:
> **403 dönmeli.** 200 dönerse hemen durun ve tüm parolaları değiştirin
> (veritabanı, PayTR, e-posta).

---

## 3. Kurulum

### 3.1 Depoyu bağlayın

cPanel → **Git Version Control** → Create.

- Clone URL: deponun HTTPS adresi
- Repository Path: `/home/KULLANICI/repositories/zeys`

> cPanel klon adresinde parola kabul etmiyor. SSH yoksa deploy key de
> üretilemez → **depo pratikte public olmak zorunda.** Bu yüzden `.env`
> asla commit'lenmemeli; git geçmişinde geçmiş bir parola varsa **yanmış
> sayılmalı** ve değiştirilmelidir.

### 3.2 `.cpanel.yml` yollarını doldurun

`/home/KULLANICI/` yazan üç satırı gerçek cPanel kullanıcı adıyla
değiştirin, commit'leyip push'layın. Kullanıcı adı cPanel ana ekranında
"Kullanıcı Adı" olarak görünür.

Doldurduktan sonra:

```bash
php artisan test --filter=DeployYapilandirmaTest
```

### 3.3 Veritabanını oluşturun

cPanel → MySQL Databases: veritabanı + kullanıcı oluşturun, kullanıcıya
**ALL PRIVILEGES** verin.

### 3.4 `.env` dosyasını oluşturun

Dosya Yöneticisi → `public_html/zeys_app/` → Ayarlar'dan **Gizli
Dosyaları Göster**'i açın → yeni dosya `.env`.

İçeriği `.env.production.example` dosyasından kopyalayıp doldurun.

`APP_KEY` için yerelde üretip yapıştırın:

```bash
php artisan key:generate --show
```

**Doldurulması zorunlu alanlar:**

| Alan | Nereden |
|---|---|
| `APP_URL` | Alan adınız (https ile) |
| `DB_*` | 3.3'te oluşturduğunuz veritabanı — `DB_HOST=localhost` |
| `MAIL_*` | cPanel e-posta hesabı |
| `SHOP_SATICI_UNVAN`, `MERSIS`, `VERGI_*`, `TELEFON`, `EPOSTA` | Firma bilgileri — **boş kalırsa yasal metinlerde `[GİRİLMEDİ]` görünür** |
| `PAYTR_*` | PayTR mağaza paneli |
| `SHOP_SIPARIS_BILDIRIM` | Yeni sipariş bildiriminin gideceği adres |

### 3.5 `vendor/` klasörünü taşıyın (Composer yoksa)

```bash
git add -f vendor
git commit -m "vendor klasorunu depoya ekle (sunucuda composer yok)"
```

Ardından oynak dosyaları donduruyoruz ki her `composer install` sonrası
gürültü çıkmasın:

```bash
git update-index --skip-worktree vendor/composer/installed.json
git update-index --skip-worktree vendor/composer/installed.php
git update-index --skip-worktree vendor/composer/autoload_static.php
git update-index --skip-worktree vendor/composer/autoload_real.php
git update-index --skip-worktree vendor/composer/autoload_classmap.php
git update-index --skip-worktree vendor/composer/autoload_files.php
```

> Sunucuda Composer **varsa** bu adımı atlayın; `.cpanel.yml` zaten
> `composer install` çalıştırıyor.

### 3.6 Derlenmiş varlıkları commit'leyin

Sunucuda Node yok, `public/build/` depoda olmalı:

```bash
npm run build
git add -f public/build
git commit -m "derlenmis varliklar"
```

### 3.7 Yayına alın

cPanel → Git Version Control → Manage → **iki düğme, sırayla**:

1. **Update from Remote** — commit'leri çeker
2. **Deploy HEAD Commit** — `.cpanel.yml` görevlerini çalıştırır

> Yalnız ikincisine basmak **eski kodu yeniden kurar.** Her deploy'dan
> sonra **Last Deployed SHA**'nın değiştiğini doğrulayın.

### 3.8 Yönetici hesabı

SSH varsa:

```bash
cd ~/public_html/zeys_app && php artisan zeys:yonetici
```

SSH yoksa `.cpanel.yml`'ye geçici bir görev ekleyip **bir kez** deploy
edin, sonra satırı **kaldırın**:

```
- cd $APPPATH && $PHPBIN artisan zeys:yonetici --ad="Ad Soyad" --eposta="eposta@ornek.com" >> $LOG 2>&1
```

Üretilen parola `deploy-son.log` dosyasına yazılır. **Okuyup kaydedin,
sonra o satırı günlükten silin.**

> Yeni yöneticiyi elle `users` tablosuna eklerseniz `role` sütununu
> `admin` yapmayı unutmayın — aksi hâlde panele giremez (bkz. 6-H).

### 3.9 Yasal metinleri yayımlayın

```bash
php artisan db:seed --class=LegalDocumentSeeder
```

Sonra panelden **Yasal Metinler** bölümüne girip metinleri okuyun.

> ⚠️ Şablonlar 6502 sayılı kanunun istediği başlıkları taşır ama
> **hukukçu onayından geçmemiştir.** Yayına çıkmadan önce okutun.

### 3.10 PayTR callback adresini tanımlayın

PayTR mağaza panelinde bildirim (callback) adresini **elle** girin:

```
https://ALANADI/paytr/callback
```

Bu yapılmazsa ödemeler alınır ama sipariş "ödendi" olmaz.

### 3.11 Zamanlanmış işler (cron) — ZORUNLU

cPanel → **Cron Jobs** → her dakika çalışacak şu satırı ekleyin:

```
cd /home/KULLANICI/public_html/zeys_app && php artisan schedule:run >> /dev/null 2>&1
```

Bu olmazsa **takılı rezervler temizlenmez**: müşteri PayTR sayfasını
kapatıp giderse o siparişin rezervi sonsuza kadar stok tutar, beden
vitrinde "tükendi" görünür ama rafta durur. `zeys:rezerv-temizle` 15
dakikada bir 90 dakikadan eski bekleyen ödemelerin rezervini bırakır.

Cron'un çalıştığını panonun **"Takılı rezerv"** kartından izleyin;
sürekli sıfırdan büyükse cron çalışmıyordur.

---

## 4. Kurulum sonrası kontrol listesi

**Güvenlik**
- [ ] `https://ALANADI/zeys_app/.env` → **403**
- [ ] `APP_DEBUG=false`
- [ ] `https://ALANADI/robots.txt` → gerçek kurallar (tam `Disallow: /` değil)

**Vitrin**
- [ ] Ana sayfa açılıyor, logo görünüyor
- [ ] Ürün sayfasında beden/renk seçici çalışıyor
- [ ] Sepete ekleme ve kasa açılıyor
- [ ] `https://ALANADI/sitemap.xml` geçerli XML

**Panel**
- [ ] `/admin/login` açılıyor ve giriş yapılabiliyor
- [ ] Ürün ekleyip kombinasyon üretilebiliyor

**Ödeme**
- [ ] PayTR panelinde callback adresi tanımlı
- [ ] `PAYTR_TEST_MODE=1` ile test kartıyla uçtan uca sipariş geçiyor
      (`4109 1045 4589 8068`, CVV `001`, `01/29`, 3D kodu `111111`)
- [ ] Sipariş "ödendi" oluyor ve stok düşüyor
- [ ] Onay e-postası geliyor
- [ ] `PAYTR_TEST_MODE=0` yapıldı

**Zamanlanmış işler**
- [ ] Cron kuruldu (3.11), panodaki "Takılı rezerv" kartı 0

**Ayarlar**
- [ ] Panel → Site Ayarları'nda satıcı bilgileri girildi
- [ ] "Yasal metinleri doldur" ile `[GİRİLMEDİ]` yer tutucuları kapatıldı

**Yasal**
- [ ] Metinlerde `[GİRİLMEDİ]` yer tutucusu kalmadı
- [ ] Metinler hukukçuya okutuldu
- [ ] ETBİS kaydı yapıldı

---

## 5. Sonraki deploy'lar

```bash
npm run build
git add -f public/build
git commit -m "..."
git push
```

Sonra cPanel'de **Update from Remote** → **Deploy HEAD Commit**.

---

## 6. Bilinen tuzaklar

Hiçbiri açık hata vermez. Çoğu referans projede saatler yedi.

### A. Geçersiz `.cpanel.yml` deploy'u SESSİZCE düşürür

Görev metninde `iki nokta + boşluk` geçerse cPanel dosyayı reddeder.
Deploy düşer ama **"Last Deployed" son BAŞARILI commit'te kalır** —
ekranda her şey yolunda görünür, kod eskidir.

`echo` metinlerinde `:` yerine `=` kullanın. Her görev **tek satır**.
Bu kural teste bağlı: `php artisan test --filter=DeployYapilandirmaTest`

### B. Config önbelleği `env()`'i öldürür

`artisan optimize` sonrası Laravel `.env` dosyasını **hiç yüklemez**;
`config/` **dışındaki** her `env()` çağrısı `null` döner.

Bu projede kural: `.env`'den okunan her değer `config/shop.php` ya da
`config/paytr.php` üzerinden geçer, uygulama kodu daima `config()`
kullanır.

### C. `DB_HOST=127.0.0.1` reddedilir

cPanel yetkiyi `@localhost` verir; TCP bağlantısı başka bir kimlik
sayılır. **`localhost`** yazın.

### D. Sembolik bağ takip edilmiyorsa görseller açılmaz

`public_html/storage` bağı çalışmıyorsa ürün fotoğrafları 404 verir.
Çözüm: `public_html/storage` içinde **gerçek bir klasör** oluşturup
dosya yolunu oraya yönlendirin.

### E. `vendor`'a PHP sürümü gömülür

Yerel PHP sunucudan yeniyse `platform_check.php` "8.4.1 gerekir" der ve
site **hiç açılmaz** (beyaz ekran).

Bu proje kurulurken bu hata zaten bir kez patladı: yerel PHP 8.5 ile
üretilen lock dosyası `symfony/console` 8.4+ istiyordu; ağaç 8.3 için
yeniden çözüldü.

### F. Filament, Livewire'ı rastgele önekle servis eder

`/livewire-172643c6/update` gibi. Bakım perdesinin geçiş listesinde
`livewire*` olmalı; `livewire/*` **eşleşmez**. Belirti: perde açıkken
panele giriş yapılamıyor ve **yanlış parolada bile hata çıkmıyor**.

### G. PayTR dönüş yolu iFrame içinden POST edilir

Çerez `SameSite=Lax` olduğu için oturum gelmez; `StartSession` bunu
"oturum yok" sanıp **boş bir oturum açar ve müşterinin çerezini ezer**.

Bu projede `paytr/callback` ve `odeme/donus` rotaları `web` grubunun
**dışında** (`routes/paytr.php`), oturum katmanı hiç çalışmıyor. CSRF
muafiyet listesine yazmak **yetmez**.

### H. Panel erişimi `FilamentUser` olmadan canlıda kapalıdır

Filament 5'te `User` modeli `FilamentUser` arayüzünü uygulamıyorsa panel
yalnızca `local` ortamda açılır — canlıda **hiç kimse giremez**.

Bu projede `User::canAccessPanel()` `role=admin`'e bağlı.

### I. `Color::hex()` markanın rengini bozar

Filament'te renk verirken kullanmayın — yalnız hue'yu alıp kendi
rampasını kurar. Bu projede ton merdiveni elle yazıldı
(`AdminPanelProvider`).

### J. E-posta gönderimi callback'i düşürmemeli

PayTR gövdesi "OK" olmayan her yanıtı başarısız sayıp bildirimi tekrar
gönderir. `Notifier` her gönderimi try/catch içinde tutuyor; posta
gitmezse yalnızca günlüğe yazılıyor.

---

### K. Yedeklenmesi gereken iki klasör

Veritabanı dışında yeniden üretilemeyen dosyalar:

- `zeys_app/storage/app/public/` — ürün, kategori, slayt fotoğrafları
- `zeys_app/storage/app/private/` — **fatura PDF'leri** (kişisel veri;
  herkese açık değildir, müşteri imzalı bağlantıyla indirir)

Deploy bu klasörlere dokunmaz (`.cpanel.yml` `storage/`'ı kopyalamaz),
ama sunucu taşınırken ya da hesap yenilenirken elle taşınmalı. cPanel
yedeklemesi (Yedekleme Sihirbazı → Ana Dizin) ikisini de kapsar.

## 7. Deploy takılırsa

İlk bakılacak yer:

```
/home/KULLANICI/deploy-son.log
```

Her adım oraya yazıyor (`--- 3/7 klasoru webe kapat ---` gibi), hangi
adımda durduğu görünür. cPanel'in kendi günlüğünü aramaya gerek yok.

Günlük **hiç oluşmadıysa** `.cpanel.yml` reddedilmiş demektir → **tuzak A**.
