<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Yönetici hesabı oluşturur.
 *
 * Parola DEPOYA YAZILMAZ: verilmezse rastgele üretilip yalnızca ekrana
 * basılır. Depo cPanel kısıtı yüzünden pratikte public olmak zorunda ve
 * git geçmişi de okunabilir — bir kez commit'lenen parola yanmış sayılır.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'zeys:yonetici
        {--ad= : Ad soyad}
        {--eposta= : E-posta adresi}
        {--parola= : Parola (verilmezse rastgele üretilir)}';

    protected $description = 'Panele girecek yönetici hesabı oluşturur ya da parolasını sıfırlar';

    public function handle(): int
    {
        $ad = $this->option('ad') ?: $this->ask('Ad soyad');
        $eposta = $this->option('eposta') ?: $this->ask('E-posta');

        $uretildi = false;
        $parola = $this->option('parola');

        if (! $parola) {
            $parola = $this->rastgeleParola();
            $uretildi = true;
        }

        $dogrulama = Validator::make(
            ['ad' => $ad, 'eposta' => $eposta, 'parola' => $parola],
            [
                'ad' => ['required', 'string', 'max:120'],
                'eposta' => ['required', 'email', 'max:190'],
                'parola' => ['required', Password::min(10)],
            ]
        );

        if ($dogrulama->fails()) {
            foreach ($dogrulama->errors()->all() as $hata) {
                $this->error($hata);
            }

            return self::FAILURE;
        }

        $kullanici = User::updateOrCreate(
            ['email' => $eposta],
            ['name' => $ad, 'password' => Hash::make($parola), 'role' => 'admin'],
        );

        $this->newLine();
        $this->info($kullanici->wasRecentlyCreated ? 'Yönetici oluşturuldu.' : 'Mevcut hesap güncellendi.');
        $this->line('  E-posta: '.$kullanici->email);

        if ($uretildi) {
            $this->line('  Parola : '.$parola);
            $this->newLine();
            $this->warn('Bu parola bir daha gösterilmeyecek. Şimdi kaydedin.');
        }

        return self::SUCCESS;
    }

    private function rastgeleParola(int $uzunluk = 16): string
    {
        // Karışabilen karakterler (0/O, 1/l/I) bilerek dışarıda
        $havuz = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $parola = '';

        for ($i = 0; $i < $uzunluk; $i++) {
            $parola .= $havuz[random_int(0, strlen($havuz) - 1)];
        }

        return $parola;
    }
}
