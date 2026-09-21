<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Panel formlarında tarih gösterimi.
 *
 * TUZAK: Filament form durumu model niteliklerinden DİZGİ olarak dolar
 * (datetime cast'i olsa bile, JSON serileştirmesiyle UTC ISO dizgisi
 * gelir). `$state->format()` yazmak dizgide çöküyor — bu yüzden
 * panelde her siparişin detay sayfası 500 veriyordu.
 *
 * Bu yardımcı Carbon, DateTime, dizgi ve null'ı kabul eder; dizgiyi
 * uygulama saat dilimine (Europe/Istanbul) çevirerek gösterir.
 */
final class Tarih
{
    public static function goster(mixed $deger, string $bicim = 'd.m.Y H:i', string $bos = '—'): string
    {
        if ($deger === null || $deger === '') {
            return $bos;
        }

        try {
            $tarih = $deger instanceof DateTimeInterface
                ? Carbon::instance($deger)
                : Carbon::parse((string) $deger);

            return $tarih->timezone(config('app.timezone'))->format($bicim);
        } catch (Throwable) {
            return (string) $deger;
        }
    }
}
