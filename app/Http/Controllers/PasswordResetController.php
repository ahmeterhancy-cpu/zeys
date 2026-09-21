<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Müşteri parola sıfırlama.
 *
 * Laravel'in parola aracısı kullanılıyor (tek kullanımlık, süreli jeton;
 * aynı adrese 60 sn içinde ikinci istek kısılır). E-posta markalı ve
 * Türkçe: bkz. User::sendPasswordResetNotification.
 */
class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('vitrin.hesap.parola-unuttum');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['eposta' => ['required', 'email']]);

        Password::sendResetLink(['email' => $request->input('eposta')]);

        /*
         * Sonuç ne olursa olsun AYNI mesaj: hesap yoksa "böyle bir hesap
         * yok" demek, e-posta deneyerek hangi adreslerin kayıtlı olduğunu
         * öğrenmeye izin verir. Giriş ve sipariş sorgulamada da aynı ilke.
         */
        return back()->with('bilgi',
            'Bu adrese kayıtlı bir hesap varsa, parola sıfırlama bağlantısı gönderildi. '
            .'Gelen kutunuzu (ve istenmeyen klasörünü) kontrol edin.'
        );
    }

    public function resetForm(Request $request, string $token)
    {
        return view('vitrin.hesap.parola-sifirla', [
            'token' => $token,
            'eposta' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $veri = $request->validate([
            'token' => ['required'],
            'eposta' => ['required', 'email'],
            'parola' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $sonuc = Password::reset(
            [
                'email' => $veri['eposta'],
                'password' => $veri['parola'],
                'password_confirmation' => $request->input('parola_confirmation'),
                'token' => $veri['token'],
            ],
            function (User $kullanici, string $parola) {
                $kullanici->forceFill([
                    'password' => Hash::make($parola),
                    // Açık "beni hatırla" oturumları geçersiz olsun
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($kullanici));
            }
        );

        if ($sonuc !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('eposta'))->withErrors([
                'eposta' => 'Bağlantı geçersiz ya da süresi dolmuş. Yeni bir bağlantı isteyin.',
            ]);
        }

        return redirect()->route('login')->with('bilgi', 'Parolanız değişti, yeni parolanızla giriş yapabilirsiniz.');
    }
}
