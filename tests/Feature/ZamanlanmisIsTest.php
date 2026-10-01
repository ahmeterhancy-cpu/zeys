<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Zamanlanmış işler paylaşımlı barındırmada da çalışmalı.
 */
class ZamanlanmisIsTest extends TestCase
{
    public function test_isler_ayri_surec_acmadan_calisir(): void
    {
        /*
         * GERILEME (canlida gorundu): Schedule::command(...) isi ayri bir
         * surecte calistiriyor; bu sunucuda proc_open KAPALI oldugu icin
         * cron her 15 dakikada "The Process class relies on proc_open"
         * hatasi veriyor, rezerv temizleme HIC calismiyordu — takili
         * rezervler sonsuza kadar stok tutuyordu.
         */
        $olaylar = app(Schedule::class)->events();

        $this->assertNotEmpty($olaylar, 'Zamanlanmış iş tanımlı değil.');

        foreach ($olaylar as $olay) {
            $this->assertInstanceOf(
                CallbackEvent::class,
                $olay,
                'Zamanlanmış iş ayrı süreç açıyor; proc_open kapalı sunucuda çalışmaz.'
            );
        }
    }

    public function test_rezerv_temizleme_zamanlanmis(): void
    {
        $adlar = collect(app(Schedule::class)->events())->map->description->filter()->values();

        $this->assertTrue(
            $adlar->contains(fn ($ad) => str_contains((string) $ad, 'rezerv')),
            'Rezerv temizleme işi zamanlanmamış: takılı rezervler stok tutar.'
        );
    }
}
