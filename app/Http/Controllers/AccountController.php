<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $siparisler = Order::where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return view('vitrin.hesap.ozet', compact('siparisler'));
    }

    /**
     * Hesap sahibinin kendi siparişi.
     *
     * Misafir sorgulamasındaki imzalı adresin aksine burada sahiplik
     * kontrolü yapılıyor: başkasının sipariş kimliğini deneyen biri
     * 404 alır (403 değil — siparişin var olduğunu bile ele vermeyelim).
     */
    public function order(Request $request, string $number)
    {
        $order = Order::where('number', $number)
            ->where('user_id', $request->user()->id)
            ->with(['items.variant.product', 'returnRequests'])
            ->first();

        if (! $order) {
            throw new NotFoundHttpException;
        }

        return view('vitrin.hesap.siparis', compact('order'));
    }

    public function addresses(Request $request)
    {
        $adresler = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->get();

        return view('vitrin.hesap.adresler', compact('adresler'));
    }

    public function storeAddress(Request $request)
    {
        $veri = $request->validate([
            'baslik' => ['nullable', 'string', 'max:60'],
            'ad' => ['required', 'string', 'max:120'],
            'telefon' => ['required', 'string', 'max:30'],
            'adres' => ['required', 'string', 'max:255'],
            'adres2' => ['nullable', 'string', 'max:255'],
            'ilce' => ['required', 'string', 'max:80'],
            'il' => ['required', 'string', 'max:80'],
            'posta_kodu' => ['nullable', 'string', 'max:12'],
            'varsayilan' => ['nullable'],
        ]);

        DB::transaction(function () use ($veri, $request) {
            $varsayilan = (bool) ($veri['varsayilan'] ?? false);

            // Tek varsayılan adres olabilir
            if ($varsayilan) {
                Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            Address::create([
                'user_id' => $request->user()->id,
                'title' => $veri['baslik'] ?? null,
                'name' => $veri['ad'],
                'phone' => $veri['telefon'],
                'line1' => $veri['adres'],
                'line2' => $veri['adres2'] ?? null,
                'district' => $veri['ilce'],
                'city' => $veri['il'],
                'postal_code' => $veri['posta_kodu'] ?? null,
                'is_default' => $varsayilan,
            ]);
        });

        return redirect()->route('account.addresses')->with('bilgi', 'Adres kaydedildi.');
    }

    public function destroyAddress(Request $request, Address $address)
    {
        // Sahiplik kontrolü: başkasının adresi silinemez
        if ($address->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        $address->delete();

        return redirect()->route('account.addresses')->with('bilgi', 'Adres silindi.');
    }
}
