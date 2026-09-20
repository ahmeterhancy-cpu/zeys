<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\StockAlerts;
use Illuminate\Http\Request;

class StockInquiryController extends Controller
{
    public function store(Request $request, StockAlerts $alerts)
    {
        $veri = $request->validate([
            'variant_id' => ['required', 'integer'],
            'eposta' => ['required', 'email', 'max:190'],
        ]);

        $variant = ProductVariant::query()
            ->whereKey($veri['variant_id'])
            ->where('is_active', true)
            ->with('product')
            ->first();

        if (! $variant || ! $variant->product?->is_active) {
            return back()->with('hata', 'Bu ürün artık satışta değil.');
        }

        /*
         * Stoğu olan varyant için kayıt almıyoruz: müşteri "haber ver"
         * dediğini sanıp beklerken ürün zaten alınabilir durumda olurdu
         * ve hiç bildirim gelmezdi.
         */
        if ($variant->available_stock > 0) {
            return back()->with('bilgi', 'Bu beden şu an stokta, hemen sipariş verebilirsiniz.');
        }

        $alerts->subscribe($variant, $veri['eposta']);

        return back()->with('bilgi', 'Kaydınız alındı. Bu beden geldiğinde size e-posta göndereceğiz.');
    }
}
