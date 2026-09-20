<?php

/*
 * public_html/index.php — cPanel yerlesimi icin giris dosyasi.
 *
 * Bu dosya YALNIZCA alan adinin kok dizini degistirilemiyorsa gerekir.
 * O durumda Laravel'in tamami public_html/zeys_app/ altinda durur, web
 * kokunde yalniz public/ icerigi ve bu dosya bulunur.
 *
 * Kok dizin degistirilebiliyorsa bu dosyaya HIC GEREK YOK: kok dizini
 * dogrudan uygulamanin public/ klasorune yoneltin, Laravel'in kendi
 * public/index.php dosyasi calisir. O yol hem daha temiz hem daha
 * guvenli — asagidaki .htaccess hilesine bagimli kalmazsiniz.
 *
 * zeys_app/.htaccess klasoru webe tamamen kapatir (deploy her seferinde
 * yeniden koyar). O dosya olmazsa .env disaridan okunabilir.
 *
 * Laravel'in kendi public/index.php dosyasi oldugu gibi duruyor;
 * yerel gelistirme onu kullanmaya devam ediyor.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app_base = __DIR__.'/zeys_app';

// Bakim kipi (php artisan down) — Laravel'in kendi perdesi
if (file_exists($maintenance = $app_base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $app_base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
