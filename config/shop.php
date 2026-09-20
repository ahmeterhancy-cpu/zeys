<?php

/*
 * Magaza ayarlari.
 *
 * TUZAK: config onbellegi (`artisan optimize`) sonrasi Laravel .env dosyasini
 * HIC yuklemez. config/ disindaki her env() cagrisi null doner. Bu yuzden
 * .env'den okunan her deger BU dosyadan gecmek zorunda; uygulama kodu
 * daima config('shop.*') kullanir, asla env().
 */

return [

    // Kimlik
    'ad' => env('SHOP_AD', 'Zeys Fashion House'),
    'alan_adi' => env('SHOP_ALAN_ADI', ''),

    // Para birimi — satis bolgesi Turkiye
    'para_birimi' => 'TRY',
    'para_simgesi' => '₺',

    /*
     * Kargo: tek ucret + ucretsiz esigi.
     *
     * Para her yerde TL cinsinden decimal(10,2) tutulur — varyant fiyati,
     * siparis toplami ve burasi ayni birimde olmak ZORUNDA. Kurus/TL
     * karisimi sessiz 100 kat hatalara yol acar.
     */
    'kargo' => [
        'ucret' => (float) env('SHOP_KARGO_UCRET', 99.00),
        'ucretsiz_esigi' => (float) env('SHOP_KARGO_UCRETSIZ_ESIGI', 1500.00),
        'firma' => env('SHOP_KARGO_FIRMA', ''),
    ],

    // Stok
    'dusuk_stok_esigi' => (int) env('SHOP_DUSUK_STOK_ESIGI', 3),

    // 6502 sayili kanun / Mesafeli Sozlesmeler Yonetmeligi
    'cayma_hakki_gun' => 14,

    // Sosyal
    'sosyal' => [
        'instagram' => env('SHOP_INSTAGRAM', 'zeysfashionhouse'),
    ],

    // Satici bilgileri — yasal metinlerde ve faturada gorunur
    'satici' => [
        'unvan' => env('SHOP_SATICI_UNVAN', ''),
        'adres' => env('SHOP_SATICI_ADRES', 'Cumhuriyet Mahallesi 3049. Sokak No:3, Edirne Life 5 — Edirne/Merkez'),
        'telefon' => env('SHOP_SATICI_TELEFON', ''),
        'eposta' => env('SHOP_SATICI_EPOSTA', ''),
        'mersis' => env('SHOP_SATICI_MERSIS', ''),
        'vergi_dairesi' => env('SHOP_SATICI_VERGI_DAIRESI', ''),
        'vergi_no' => env('SHOP_SATICI_VERGI_NO', ''),
    ],

    // Bakim perdesi
    'bakim_modu' => (bool) env('SHOP_BAKIM_MODU', false),

];
