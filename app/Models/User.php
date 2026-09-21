<?php

namespace App\Models;

use App\Mail\ParolaSifirlama;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FilamentUser BILEREK uygulaniyor.
 *
 * Bu arayuz olmadan Filament panele erisimi yalnizca `local` ortamda
 * aciyor; canliya (production) cikildiginda HIC KIMSE panele giremiyor.
 * Testte 403 olarak yakalandi — kesfedilmeseydi deploy gunu ortaya
 * cikacakti.
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const ROLLER = [
        'customer' => 'Müşteri',
        'staff' => 'Personel',
        'admin' => 'Yönetici',
    ];

    /** Panele yöneticiler ve personel girer; personelin yetkisi kısıtlı (App\Support\Yetki). */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->panelde();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** Panel kullanıcısı mı (yönetici ya da personel). */
    public function panelde(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /**
     * Laravel'in İngilizce varsayılan bildirimi yerine markalı Türkçe
     * e-posta. Gönderim hatası isteği düşürmez; kullanıcıya yine aynı
     * "gönderildi" mesajı gösterilir (hesap varlığını ele vermemek için).
     */
    public function sendPasswordResetNotification($token): void
    {
        $baglanti = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $dakika = (int) config('auth.passwords.users.expire', 60);

        try {
            Mail::to($this->email)->send(new ParolaSifirlama($baglanti, $dakika));
        } catch (Throwable $e) {
            Log::error('Parola sıfırlama e-postası gönderilemedi: '.$e->getMessage());
        }
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}
