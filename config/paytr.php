<?php

return [
    'merchant_id' => env('PAYTR_MERCHANT_ID', ''),
    'merchant_key' => env('PAYTR_MERCHANT_KEY', ''),
    'merchant_salt' => env('PAYTR_MERCHANT_SALT', ''),

    // 1 iken PayTR test ortamina gider, gercek para cekilmez.
    'test_mode' => (int) env('PAYTR_TEST_MODE', 1),

    'token_endpoint' => 'https://www.paytr.com/odeme/api/get-token',
    'iframe_url' => 'https://www.paytr.com/odeme/guvenli/',
    'refund_endpoint' => 'https://www.paytr.com/odeme/iade',

    'timeout_limit' => 30,
    'no_installment' => (int) env('PAYTR_NO_INSTALLMENT', 0),
    'max_installment' => (int) env('PAYTR_MAX_INSTALLMENT', 0),
    'currency' => 'TL',
    'lang' => 'tr',

    /*
     * TLS dogrulamasi.
     *
     * Donor projede Http::withoutVerifying() kullaniliyordu; muhtemelen
     * Windows'ta PHP'nin CA paketi tanimsiz oldugu icin cURL 60 hatasini
     * asmak ucun. Ama bu bir ODEME ucu — sertifika dogrulamasini kapatmak
     * araya girme (MITM) riskini acar. Burada varsayilan DOGRULA'dir;
     * yerelde CA paketi yoksa .env ile kapatilabilir, canlida asla.
     *
     * Kalici cozum: php.ini icinde curl.cainfo = <cacert.pem yolu>.
     */
    'verify_tls' => (bool) env('PAYTR_VERIFY_TLS', true),
    'ca_bundle' => env('PAYTR_CA_BUNDLE'), // opsiyonel cacert.pem yolu
];
