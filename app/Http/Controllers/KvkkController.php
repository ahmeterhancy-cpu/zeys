<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\StockInquiry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * KVKK md. 11 — ilgili kişinin hakları: verilerine erişim ve silinmesini
 * isteme.
 *
 * SİLME SINIRI: sipariş ve fatura kayıtları vergi mevzuatı gereği
 * (VUK, TTK) yasal süre boyunca SAKLANMAK ZORUNDA. Bu yüzden hesap
 * silindiğinde siparişler silinmez, hesaptan AYRILIR (user_id boşalır).
 * Adresler, stok bildirim kayıtları ve hesap silinir. Ekranda müşteriye
 * açıkça söyleniyor — "her şey silindi" demek yanlış olurdu.
 *
 * Saklama süreleri hukukçuya teyit ettirilmeli.
 */
class KvkkController extends Controller
{
    public function index()
    {
        return view('vitrin.hesap.verilerim');
    }

    /** Kişisel verilerin makinece okunabilir kopyası (JSON). */
    public function export(Request $request)
    {
        $k = $request->user();

        $veri = [
            'olusturma' => now()->toIso8601String(),
            'magaza' => config('shop.ad'),
            'hesap' => [
                'ad' => $k->name,
                'eposta' => $k->email,
                'kayit_tarihi' => $k->created_at?->toIso8601String(),
            ],
            'adresler' => Address::where('user_id', $k->id)->get()
                ->map(fn (Address $a) => $a->toSnapshot() + ['baslik' => $a->title])
                ->all(),
            'siparisler' => Order::where('user_id', $k->id)
                ->with(['items', 'returnRequests.items', 'reviews.product'])
                ->get()
                ->map(fn (Order $o) => [
                    'numara' => $o->number,
                    'tarih' => $o->created_at?->toIso8601String(),
                    'durum' => $o->status_label,
                    'toplam' => (string) $o->grand_total,
                    'teslimat_adresi' => $o->shipping_address,
                    'fatura' => $o->billing_address,
                    'urunler' => $o->items->map(fn ($i) => [
                        'urun' => $i->name,
                        'kombinasyon' => $i->variant_label,
                        'adet' => $i->quantity,
                        'tutar' => (string) $i->line_total,
                    ])->all(),
                    'onaylanan_sozlesme_surumu' => $o->contract_version,
                    'onay_ip' => $o->contract_ip,
                    'iade_talepleri' => $o->returnRequests->map(fn ($r) => [
                        'numara' => $r->number,
                        'tur' => $r->type,
                        'durum' => $r->status_label,
                    ])->all(),
                    'degerlendirmeler' => $o->reviews->map(fn ($y) => [
                        'urun' => $y->product?->name,
                        'puan' => $y->rating,
                        'baslik' => $y->title,
                        'yorum' => $y->body,
                        'gorunen_ad' => $y->author_name,
                        'durum' => $y->status_label,
                    ])->all(),
                ])->all(),
            'stok_bildirimleri' => StockInquiry::where('email', mb_strtolower($k->email))
                ->with('variant.product')
                ->get()
                ->map(fn ($s) => [
                    'urun' => $s->variant?->product?->name,
                    'sku' => $s->variant?->sku,
                    'kayit' => $s->created_at?->toIso8601String(),
                ])->all(),
        ];

        $dosya = 'zeys-verilerim-'.now()->format('Ymd').'.json';

        return response()->json($veri, 200, [
            'Content-Disposition' => 'attachment; filename="'.$dosya.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'parola' => ['required', 'string'],
            'onay' => ['accepted'],
        ], [
            'onay.accepted' => 'Hesabın silineceğini onaylamanız gerekir.',
        ]);

        $k = $request->user();

        if (! Hash::check($request->input('parola'), $k->password)) {
            throw ValidationException::withMessages(['parola' => 'Parola hatalı.']);
        }

        /*
         * Yönetici hesabı vitrinden silinemez: son yöneticiyi silmek paneli
         * kilitler. Yönetici hesapları panelden (Kullanıcılar) yönetilir.
         */
        if ($k->isAdmin()) {
            throw ValidationException::withMessages([
                'parola' => 'Yönetici hesapları buradan silinemez; panelden yönetin.',
            ]);
        }

        $kimlik = $k->id;
        $eposta = mb_strtolower($k->email);

        /*
         * SIRA ÖNEMLİ: önce çıkış, SONRA silme.
         *
         * Auth::logout() "beni hatırla" jetonunu yenilemek için kullanıcı
         * modelini KAYDEDİYOR. Silinmiş modelde save() satırı yeniden
         * EKLİYOR — silme sonra yapılsaydı müşteriye "hesabınız silindi"
         * denip hesap sessizce geri gelirdi (testte yakalandı).
         */
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($kimlik, $eposta) {
            /*
             * Yorumlar saklama yükümlülüğü olan bir kayıt değil, müşterinin
             * kendi içeriği: silinir. Model üzerinden (sorgu değil) — ürün
             * puan önbelleği her silmede yeniden hesaplansın.
             */
            ProductReview::whereIn('order_id', Order::where('user_id', $kimlik)->select('id'))
                ->get()
                ->each->delete();

            // Siparişler SİLİNMEZ, hesaptan ayrılır (yasal saklama)
            Order::where('user_id', $kimlik)->update(['user_id' => null]);

            Address::where('user_id', $kimlik)->delete();
            StockInquiry::where('email', $eposta)->delete();

            // Model örneği değil sorgu: elde tutulan bir örnek sonradan
            // kaydedilse bile silinen satırı geri getiremesin
            User::whereKey($kimlik)->delete();
        });

        return redirect()->route('home')->with('bilgi',
            'Hesabınız silindi. Sipariş ve fatura kayıtlarınız yasal saklama süresi boyunca tutulacak.'
        );
    }
}
