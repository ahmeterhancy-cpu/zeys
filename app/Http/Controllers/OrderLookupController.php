<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Returns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Misafir sipariş sorgulama ve iade talebi.
 *
 * Müşteri hesabı zorunlu değil (misafir alışverişi var), bu yüzden
 * sipariş numarası + e-posta ile sorgulanıyor.
 *
 * GÜVENLİK: sipariş numarası sıralı ve tahmin edilebilir
 * (ZEY-260921-0001 → -0002). Tek başına yeterli değil, e-posta da
 * doğrulanıyor. Doğrulama sonrası İMZALI bir adrese yönlendiriliyor;
 * böylece sayfa yenilendiğinde ya da bağlantı paylaşıldığında e-posta
 * tekrar sorulmuyor ama başkasının siparişi de açılamıyor.
 */
class OrderLookupController extends Controller
{
    public function form()
    {
        return view('vitrin.siparis-sorgula');
    }

    public function lookup(Request $request)
    {
        $veri = $request->validate([
            'siparis_no' => ['required', 'string', 'max:40'],
            'eposta' => ['required', 'email', 'max:190'],
        ]);

        $order = Order::query()
            ->where('number', trim($veri['siparis_no']))
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($veri['eposta']))])
            ->first();

        if (! $order) {
            /*
             * Tek ve aynı mesaj: "sipariş yok" ile "e-posta tutmuyor"
             * ayrımı verilmiyor ki numara deneyerek hangi siparişin var
             * olduğu öğrenilemesin.
             */
            throw ValidationException::withMessages([
                'siparis_no' => 'Bu bilgilerle bir sipariş bulunamadı. Sipariş numarasını ve e-postanızı kontrol edin.',
            ]);
        }

        return redirect(URL::signedRoute('order.show', ['order' => $order->number]));
    }

    public function show(Order $order, Returns $returns)
    {
        $order->load(['items.variant.product', 'returnRequests.items.orderItem', 'reviews']);

        /*
         * Değerlendirilebilecek ürünler: teslim edilmiş siparişteki her
         * ÜRÜN bir kez (iki bedeni alınmış elbise tek yorum). Silinmiş
         * ürün (product_id boş) atlanır.
         */
        $degerlendirme = $order->delivered_at
            ? $order->items
                ->whereNotNull('product_id')
                ->unique('product_id')
                ->map(fn ($kalem) => [
                    'product_id' => $kalem->product_id,
                    'ad' => $kalem->name,
                    'yorum' => $order->reviews->firstWhere('product_id', $kalem->product_id),
                ])
                ->values()
            : collect();

        return view('vitrin.siparis', [
            'order' => $order,
            'degerlendirme' => $degerlendirme,
            'iadeEdilebilir' => $order->items->mapWithKeys(
                fn ($kalem) => [$kalem->id => $returns->returnableQuantity($kalem)]
            ),
            'caymaSonu' => $returns->withdrawalDeadline($order),
            'iadeAcilabilir' => $order->payment_status === 'paid'
                && $returns->isWithinWithdrawalPeriod($order),
        ]);
    }

    /**
     * Fatura PDF'i. İmzalı adresle (sipariş sayfasındaki bağlantı) açılır;
     * dosya gizli diskte durur, herkese açık bir yolu yoktur.
     */
    public function fatura(Order $order)
    {
        abort_unless($order->invoice_pdf && Storage::disk('local')->exists($order->invoice_pdf), 404);

        return Storage::disk('local')->download($order->invoice_pdf, 'Fatura-'.$order->invoice_number.'.pdf');
    }

    /** Müşterinin iade / değişim talebi açması. */
    public function storeReturn(Request $request, Order $order, Returns $returns)
    {
        $veri = $request->validate([
            'tur' => ['required', 'in:return,exchange'],
            'gerekce' => ['required', 'in:beden,kusurlu,yanlis-urun,vazgectim,diger'],
            'not' => ['nullable', 'string', 'max:500'],
            'kalemler' => ['required', 'array', 'min:1'],
            'kalemler.*.sec' => ['nullable'],
            'kalemler.*.adet' => ['nullable', 'integer', 'min:1'],
            'kalemler.*.degisim_varyant' => ['nullable', 'integer'],
        ]);

        $secilen = [];

        foreach ($veri['kalemler'] as $orderItemId => $detay) {
            if (empty($detay['sec'])) {
                continue;
            }

            $secilen[(int) $orderItemId] = [
                'quantity' => (int) ($detay['adet'] ?? 1),
                'exchange_variant_id' => $detay['degisim_varyant'] ?? null,
            ];
        }

        if ($secilen === []) {
            return back()->with('hata', 'En az bir ürün seçmelisiniz.');
        }

        try {
            $returns->open($order, $veri['tur'], $veri['gerekce'], $secilen, $veri['not'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('hata', $e->getMessage());
        }

        return redirect(URL::signedRoute('order.show', ['order' => $order->number]))
            ->with('bilgi', 'Talebiniz alındı. En kısa sürede size dönüş yapacağız.');
    }
}
