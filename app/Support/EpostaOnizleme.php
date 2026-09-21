<?php

namespace App\Support;

use App\Mail\BackInStock;
use App\Mail\FaturaGonderimi;
use App\Mail\OrderPlaced;
use App\Mail\OrderShipped;
use App\Mail\ParolaSifirlama;
use App\Mail\ReturnResolved;
use App\Mail\SozlesmeBelgeleri;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use Illuminate\Mail\Mailable;

/**
 * Panelde "Önizle": e-postayı son gerçek siparişle (yoksa örnek veriyle)
 * çizer. Hiçbir şey gönderilmez, veritabanına yazılmaz.
 */
class EpostaOnizleme
{
    public static function html(string $anahtar): string
    {
        return static::eposta($anahtar)->render();
    }

    public static function eposta(string $anahtar): Mailable
    {
        $siparis = Order::with('items')->latest('id')->first() ?? static::ornekSiparis();

        return match ($anahtar) {
            'siparis-alindi' => new OrderPlaced($siparis),
            'siparis-kargoda' => new OrderShipped(tap(clone $siparis, function (Order $o) {
                $o->shipping_carrier ??= 'Yurtiçi Kargo';
                $o->tracking_number ??= '123456789';
            })),
            'sozlesme-belgeleri' => new SozlesmeBelgeleri($siparis),
            'fatura' => new FaturaGonderimi(tap(clone $siparis, function (Order $o) {
                $o->invoice_number ??= 'ZEY2026000000001';
                $o->invoice_date ??= now();
                $o->invoice_pdf = null; // önizlemede ek yok
            })),
            'stokta' => new BackInStock(ProductVariant::with('product', 'optionValues.option')->first() ?? static::ornekVaryant()),
            'parola-sifirlama' => new ParolaSifirlama(url('/parola-sifirla/ornek'), 60),
            'iade-onay' => new ReturnResolved(static::ornekTalep($siparis, 'approved', false)),
            'degisim-onay' => new ReturnResolved(static::ornekTalep($siparis, 'approved', true)),
            'iade-ret' => new ReturnResolved(static::ornekTalep($siparis, 'rejected', false)),
            'iade-tamam' => new ReturnResolved(static::ornekTalep($siparis, 'completed', false)),
            'degisim-tamam' => new ReturnResolved(static::ornekTalep($siparis, 'completed', true)),
        };
    }

    private static function ornekSiparis(): Order
    {
        $o = new Order([
            'number' => 'ZEY-ORNEK-0001',
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.test',
            'customer_phone' => '5551112233',
            'shipping_address' => ['name' => 'Ayşe Yılmaz', 'line1' => 'Örnek Mah. 1', 'district' => 'Merkez', 'city' => 'Edirne'],
            'subtotal' => 2890, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2890,
        ]);
        $o->created_at = now();
        $o->setRelation('items', collect());

        return $o;
    }

    private static function ornekVaryant(): ProductVariant
    {
        $v = new ProductVariant(['sku' => 'ORNEK-M']);
        $v->setRelation('product', new Product(['name' => 'Saten Midi Elbise', 'slug' => 'ornek']));
        $v->setRelation('optionValues', collect());

        return $v;
    }

    private static function ornekTalep(Order $siparis, string $durum, bool $degisim): ReturnRequest
    {
        $t = new ReturnRequest([
            'number' => 'IAD-ORNEK-0001',
            'type' => $degisim ? 'exchange' : 'return',
            'status' => $durum,
            'refund_amount' => $degisim ? 0 : 2890,
            'admin_note' => $durum === 'rejected' ? 'Ürün kullanılmış olarak geldi; etiketi çıkarılmış.' : null,
            'exchange_carrier' => $degisim ? 'Yurtiçi Kargo' : null,
            'exchange_tracking_number' => $degisim ? '987654321' : null,
        ]);
        $t->setRelation('order', $siparis);
        $t->setRelation('items', collect());

        return $t;
    }
}
