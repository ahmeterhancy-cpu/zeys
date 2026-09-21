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
Schedule::command('zeys:rezerv-temizle')->everyFifteenMinutes()->withoutOverlapping();
