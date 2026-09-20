<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Müşteri hesabı.
 *
 * Hesap ZORUNLU DEĞİL — misafir alışverişi çalışmaya devam ediyor.
 * Hesap yalnızca sipariş geçmişini ve adres defterini kolaylaştırıyor.
 *
 * Panel erişimi ayrı: User::canAccessPanel yalnızca role=admin'e izin
 * veriyor, buradan açılan hesaplar `customer` rolünde.
 */
class AuthController extends Controller
{
    public function loginForm()
    {
        return view('vitrin.hesap.giris');
    }

    public function login(Request $request)
    {
        $veri = $request->validate([
            'eposta' => ['required', 'email'],
            'parola' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $veri['eposta'], 'password' => $veri['parola']], $request->boolean('beni_hatirla'))) {
            /*
             * Tek ve aynı mesaj: "böyle bir hesap yok" ile "parola
             * yanlış" ayrımı verilmiyor ki e-posta deneyerek hangi
             * adreslerin kayıtlı olduğu öğrenilemesin.
             */
            throw ValidationException::withMessages([
                'eposta' => 'E-posta ya da parola hatalı.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function registerForm()
    {
        return view('vitrin.hesap.kayit');
    }

    public function register(Request $request)
    {
        $veri = $request->validate([
            'ad' => ['required', 'string', 'max:120'],
            'eposta' => ['required', 'email', 'max:190', 'unique:users,email'],
            'parola' => ['required', 'confirmed', Password::min(8)],
        ], [
            'eposta.unique' => 'Bu e-posta ile bir hesap zaten var. Giriş yapmayı deneyin.',
        ]);

        $kullanici = User::create([
            'name' => $veri['ad'],
            'email' => $veri['eposta'],
            'password' => Hash::make($veri['parola']),
            'role' => 'customer',
        ]);

        Auth::login($kullanici);
        $request->session()->regenerate();

        /*
         * Aynı e-postayla daha önce MİSAFİR olarak verilmiş siparişler
         * hesaba bağlanıyor; müşteri geçmişini kaybetmiş hissetmesin.
         */
        Order::whereNull('user_id')
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($veri['eposta'])])
            ->update(['user_id' => $kullanici->id]);

        return redirect()->route('account.index')
            ->with('bilgi', 'Hesabınız oluşturuldu.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
