<?php

namespace App\Filament\Concerns;

use App\Support\Yetki;

/**
 * Kaynak/sayfa yalnız yöneticiye açık: personel menüde görmez, adresi
 * elle yazarsa 403 alır. Bkz. App\Support\Yetki.
 */
trait YalnizYonetici
{
    public static function canAccess(): bool
    {
        return Yetki::yonetici();
    }
}
