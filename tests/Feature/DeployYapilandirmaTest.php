<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * .cpanel.yml sağlık kontrolü.
 *
 * Bu dosyadaki hatalar SESSİZDİR: cPanel geçersiz YAML'ı reddeder,
 * deploy düşer ama "Last Deployed" son BAŞARILI commit'te kalır — yani
 * ekranda her şey yolunda görünür. Referans projede saatler yedi.
 * O yüzden kurallar teste bağlandı.
 */
class DeployYapilandirmaTest extends TestCase
{
    private function satirlar(): array
    {
        $yol = base_path('.cpanel.yml');

        $this->assertFileExists($yol, '.cpanel.yml bulunamadı');

        return preg_split('/\R/', file_get_contents($yol));
    }

    /** @return array<int, array{0:int,1:string}> satır no => görev metni */
    private function gorevler(): array
    {
        $gorevler = [];
        $tasksIcinde = false;

        foreach ($this->satirlar() as $no => $satir) {
            if (preg_match('/^\s*tasks:\s*$/', $satir)) {
                $tasksIcinde = true;

                continue;
            }

            if (! $tasksIcinde) {
                continue;
            }

            if (preg_match('/^\s*-\s(.*)$/', $satir, $e)) {
                $gorevler[] = [$no + 1, $e[1]];
            }
        }

        return $gorevler;
    }

    public function test_gorev_metinlerinde_iki_nokta_bosluk_yok(): void
    {
        $gorevler = $this->gorevler();

        $this->assertNotEmpty($gorevler, 'Hiç görev bulunamadı — dosya yapısı bozulmuş olabilir.');

        foreach ($gorevler as [$no, $metin]) {
            $this->assertStringNotContainsString(
                ': ',
                $metin,
                "Satır {$no}: görev metninde 'iki nokta + boşluk' var. ".
                'cPanel bunu eşleşme sanıp dosyayı reddeder ve deploy sessizce düşer. '.
                "Echo metinlerinde ':' yerine '=' kullanın.\n  {$metin}"
            );
        }
    }

    public function test_her_gorev_tek_satir(): void
    {
        foreach ($this->gorevler() as [$no, $metin]) {
            $this->assertStringNotContainsString(
                '\\',
                rtrim($metin),
                "Satır {$no}: görev satır sonu devamı (\\) içeriyor. ".
                'cPanel çok satırlı komutlarda takılıyor; her görev tek satır olmalı.'
            );
        }
    }

    public function test_yer_tutucular_doldurulmamissa_uyarir(): void
    {
        $icerik = file_get_contents(base_path('.cpanel.yml'));

        /*
         * Bu test BİLEREK "KULLANICI kalmış mı" diye bakar ve kaldıysa
         * atlanır — canlıya çıkmadan önce doldurulması gerektiğini
         * hatırlatır ama yerelde kırmızı yakmaz.
         */
        if (str_contains($icerik, '/home/KULLANICI/')) {
            $this->markTestSkipped(
                'cPanel kullanıcı adı henüz doldurulmadı (.cpanel.yml içinde /home/KULLANICI/). '.
                'Canlıya çıkmadan önce gerçek kullanıcı adıyla değiştirin.'
            );
        }

        $this->assertStringNotContainsString('KULLANICI', $icerik);
    }

    public function test_veritabani_sifirlama_gorevi_yok(): void
    {
        foreach ($this->gorevler() as [$no, $metin]) {
            foreach (['migrate:fresh', 'migrate:reset', 'db:wipe'] as $tehlikeli) {
                $this->assertStringNotContainsString(
                    $tehlikeli,
                    $metin,
                    "Satır {$no}: deploy görevinde '{$tehlikeli}' var. ".
                    'Canlıda gerçek sipariş verisi olacak; bu komut her deploy\'da siler.'
                );
            }
        }
    }

    public function test_deploy_yardimci_dosyalari_yerinde(): void
    {
        $this->assertFileExists(base_path('deploy/cpanel/app.htaccess'));
        $this->assertFileExists(base_path('deploy/cpanel/index.php'));
        $this->assertFileExists(base_path('.env.production.example'));

        // NOT: DEPLOY.md henüz yazılmadı. Proje canlıya çıkmaya hazır
        // değil; deploy belgesi, vitrin eksikleri kapandıktan sonra
        // yazılacak. Bkz. DURUM.md
    }

    public function test_uygulama_klasoru_htaccess_erisimi_kapatiyor(): void
    {
        $icerik = file_get_contents(base_path('deploy/cpanel/app.htaccess'));

        // Bu dosya olmazsa .env tarayıcıdan indirilebilir
        $this->assertStringContainsString('Require all denied', $icerik);
        $this->assertStringContainsString('Deny from all', $icerik);
    }

    public function test_env_ornegi_gercek_sir_icermiyor(): void
    {
        $icerik = file_get_contents(base_path('.env.production.example'));

        $bos = [
            'PAYTR_MERCHANT_ID',
            'PAYTR_MERCHANT_KEY',
            'PAYTR_MERCHANT_SALT',
            'DB_PASSWORD',
            'APP_KEY',
        ];

        foreach ($bos as $anahtar) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($anahtar, '/').'=\s*$/m',
                $icerik,
                "{$anahtar} örnek dosyada BOŞ olmalı. Depo public olacağı için ".
                'buraya yazılan her değer herkese açık sayılır.'
            );
        }
    }

    public function test_env_dosyasi_depoya_girmiyor(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));

        $this->assertStringContainsString('.env', $gitignore);
    }
}
