<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Cart $cart)
    {
        return view('vitrin.sepet', [
            'satirlar' => $cart->lines(),
            'araToplam' => $cart->subtotal(),
            'indirim' => $cart->discount(),
            'kargo' => $cart->shipping(),
            'toplam' => $cart->total(),
            'kupon' => $cart->coupon(),
            'kalanKargo' => $cart->freeShippingRemaining(),
        ]);
    }

    public function add(Request $request, Cart $cart)
    {
        $veri = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        /*
         * Varyant sunucuda yeniden okunur: istemciden gelen fiyat ya da
         * stok bilgisine güvenilmez, yalnızca kimlik kullanılır.
         */
        $variant = ProductVariant::query()
            ->whereKey($veri['variant_id'])
            ->where('is_active', true)
            ->with('product')
            ->first();

        if (! $variant || ! $variant->product?->is_active) {
            return back()->with('hata', 'Bu ürün artık satışta değil.');
        }

        if ($variant->available_stock < 1) {
            return back()->with('hata', 'Seçtiğiniz beden tükendi.');
        }

        $cart->add($variant, (int) ($veri['quantity'] ?? 1));

        return redirect()->route('cart.index')->with('bilgi', 'Ürün sepete eklendi.');
    }

    public function update(Request $request, Cart $cart)
    {
        $veri = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $variant = ProductVariant::find($veri['variant_id']);

        if ($variant) {
            $cart->update($variant, (int) $veri['quantity']);
        }

        return redirect()->route('cart.index');
    }

    public function remove(Request $request, Cart $cart)
    {
        $veri = $request->validate(['variant_id' => ['required', 'integer']]);

        $cart->remove((int) $veri['variant_id']);

        return redirect()->route('cart.index');
    }

    public function applyCoupon(Request $request, Cart $cart)
    {
        $veri = $request->validate(['code' => ['required', 'string', 'max:60']]);

        return $cart->applyCoupon($veri['code'])
            ? redirect()->route('cart.index')->with('bilgi', 'Kupon uygulandı.')
            : redirect()->route('cart.index')->with('hata', 'Kupon geçersiz ya da sepetiniz uygun değil.');
    }

    public function forgetCoupon(Cart $cart)
    {
        $cart->forgetCoupon();

        return redirect()->route('cart.index');
    }
}
