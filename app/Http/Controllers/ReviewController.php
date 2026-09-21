<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Müşteri ürün yorumu.
 *
 * Yalnız TESLİM EDİLMİŞ siparişin (imzalı) sipariş sayfasından yazılır —
 * yani yorum yazan kişinin ürünü gerçekten aldığı biliniyor. Yorum onay
 * bekler; yönetici panelden yayımlar.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $geri = redirect(URL::signedRoute('order.show', ['order' => $order->number]).'#degerlendirme');

        if (! $order->delivered_at) {
            return $geri->with('hata', 'Değerlendirme, sipariş teslim edildikten sonra yazılabilir.');
        }

        $urunler = $order->items()->whereNotNull('product_id')->pluck('product_id')->unique()->all();

        $veri = $request->validate([
            'product_id' => ['required', 'integer', Rule::in($urunler)],
            'puan' => ['required', 'integer', 'between:1,5'],
            'baslik' => ['nullable', 'string', 'max:120'],
            'yorum' => ['required', 'string', 'min:10', 'max:1500'],
        ], [
            'product_id.in' => 'Bu ürün siparişinizde yok.',
            'puan.required' => 'Lütfen 1 ile 5 arasında bir puan seçin.',
            'yorum.min' => 'Yorum en az 10 karakter olmalı.',
        ]);

        if ($order->reviews()->where('product_id', $veri['product_id'])->exists()) {
            throw ValidationException::withMessages([
                'yorum' => 'Bu ürün için bu siparişten zaten değerlendirme yazdınız.',
            ]);
        }

        ProductReview::create([
            'product_id' => $veri['product_id'],
            'order_id' => $order->id,
            'author_name' => ProductReview::gorunenAd($order->customer_name),
            'rating' => $veri['puan'],
            'title' => $veri['baslik'] ?? null,
            'body' => $veri['yorum'],
            'status' => 'pending',
        ]);

        return $geri->with('bilgi', 'Teşekkürler! Değerlendirmeniz onaylandıktan sonra ürün sayfasında yayımlanacak.');
    }
}
