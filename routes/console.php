<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Zamanlanmis isler.
 *
 * cPanel'de calismasi icin Cron Jobs'a HER DAKIKA su satir eklenmeli:
 *   cd /home/KULLANICI/public_html/zeys_app && php artisan schedule:run >> /dev/null 2>&1
 * Bkz. DEPLOY.md 3.11. Cron kurulmazsa takili rezervler temizlenmez.
 */
/*
 * TUZAK (bu sunucuda gorundu): Schedule::command(...) isi AYRI BIR SURECTE
 * calistirir ve paylasimli barindirmada proc_open kapali olabilir:
 * "The Process class relies on proc_open" hatasi her 15 dakikada bir
 * gunluge dusuyor, is HIC calismiyordu — takili rezervler sonsuza kadar
 * stok tutuyordu. Kapanis (closure) ayni surecte calisir.
 */
Schedule::call(fn () => Artisan::call('zeys:rezerv-temizle'))
    ->everyFifteenMinutes()
    ->name('zeys-rezerv-temizle')
    ->withoutOverlapping();
