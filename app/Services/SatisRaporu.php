<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

/**
 * Tarih aralığına göre satış özeti.
 *
 * Tanımlar — raporu okuyan muhasebeciyle aynı dili konuşmak için:
 *  - Dönem, siparişin ÖDENDİĞİ güne göre belirlenir (paid_at), oluşturulduğu
 *    güne göre değil. Gece 23:58'de açılıp 00:03'te ödenen sipariş ertesi
 *    günün satışıdır. Gün sınırları Europe/Istanbul'dur.
 *  - Ödemesi alınmamış (bekleyen/başarısız) sipariş rapora girmez.
 *  - İade: bu dönemde ÖDENEN siparişlerden bugüne dek geri ödenen tutar.
 *    Yani geçen ayın raporu, bu ay yapılan iade yüzünden değişebilir —
 *    kasıtlı: "o ayın satışı gerçekte ne kadar tuttu" sorusunun cevabı bu.
 *  - Ciro KDV dahildir; kargo ücreti dahildir, ayrıca gösterilir.
 */
class SatisRaporu
{
    /**
     * @return array{
     *     baslangic: CarbonImmutable, bitis: CarbonImmutable,
     *     siparis: int, adet: int, brut: float, kargo: float, indirim: float,
     *     iade: float, net: float, ortalama: float, iptal: int,
     *     gunluk: list<array{gun: string, siparis: int, ciro: float}>,
     *     cokSatan: list<array{sku: ?string, ad: string, varyant: ?string, adet: int, ciro: float}>
     * }
     */
    public function hesapla(string $baslangic, string $bitis): array
    {
        $bas = CarbonImmutable::parse($baslangic)->startOfDay();
        $son = CarbonImmutable::parse($bitis)->endOfDay();

        if ($son->lessThan($bas)) {
            [$bas, $son] = [$son->startOfDay(), $bas->endOfDay()];
        }

        $siparisler = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$bas, $son]);

        $toplam = (clone $siparisler)
            ->selectRaw('COUNT(*) as siparis, COALESCE(SUM(grand_total),0) as brut, COALESCE(SUM(shipping_total),0) as kargo,
                COALESCE(SUM(discount_total),0) as indirim, COALESCE(SUM(refunded_total),0) as iade')
            ->first();

        $siparis = (int) $toplam->siparis;
        $brut = round((float) $toplam->brut, 2);
        $iade = round((float) $toplam->iade, 2);

        $iptal = (clone $siparisler)->where('status', 'cancelled')->count();

        $kalemler = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.paid_at', [$bas, $son]);

        $adet = (int) (clone $kalemler)->sum('order_items.quantity');

        $cokSatan = (clone $kalemler)
            ->select('order_items.sku', 'order_items.name', 'order_items.variant_label')
            ->selectRaw('SUM(order_items.quantity) as adet, SUM(order_items.line_total) as ciro')
            ->groupBy('order_items.sku', 'order_items.name', 'order_items.variant_label')
            ->orderByDesc('adet')
            ->orderByDesc('ciro')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'sku' => $r->sku,
                'ad' => $r->name,
                'varyant' => $r->variant_label,
                'adet' => (int) $r->adet,
                'ciro' => round((float) $r->ciro, 2),
            ])
            ->all();

        return [
            'baslangic' => $bas,
            'bitis' => $son,
            'siparis' => $siparis,
            'adet' => $adet,
            'brut' => $brut,
            'kargo' => round((float) $toplam->kargo, 2),
            'indirim' => round((float) $toplam->indirim, 2),
            'iade' => $iade,
            'net' => round($brut - $iade, 2),
            'ortalama' => $siparis > 0 ? round($brut / $siparis, 2) : 0.0,
            'iptal' => $iptal,
            'gunluk' => $this->gunluk((clone $siparisler)->get(['paid_at', 'grand_total']), $bas, $son),
            'cokSatan' => $cokSatan,
        ];
    }

    /**
     * Gün gün seri. Gruplama PHP'de yapılıyor: SQLite (yerel) ile MySQL
     * (sunucu) tarih işlevleri farklı, ayrıca paid_at UTC değil uygulama
     * saat diliminde saklandığından veritabanında dönüştürmek gerekmiyor.
     *
     * @return list<array{gun: string, siparis: int, ciro: float}>
     */
    private function gunluk($siparisler, CarbonImmutable $bas, CarbonImmutable $son): array
    {
        // 93 günden uzun aralıkta günlük çizelge okunmaz; boş bırakılır
        if ($bas->diffInDays($son) > 93) {
            return [];
        }

        $gunler = [];
        for ($g = $bas; $g->lessThanOrEqualTo($son); $g = $g->addDay()) {
            $gunler[$g->toDateString()] = ['gun' => $g->toDateString(), 'siparis' => 0, 'ciro' => 0.0];
        }

        foreach ($siparisler as $s) {
            $anahtar = $s->paid_at->toDateString();
            if (isset($gunler[$anahtar])) {
                $gunler[$anahtar]['siparis']++;
                $gunler[$anahtar]['ciro'] = round($gunler[$anahtar]['ciro'] + (float) $s->grand_total, 2);
            }
        }

        return array_values($gunler);
    }

    /** Aynı raporun CSV'si (muhasebeye gönderilecek). */
    public function csv(array $r): string
    {
        $akis = fopen('php://temp', 'r+');
        fwrite($akis, "\xEF\xBB\xBF");
        $para = fn ($t) => number_format((float) $t, 2, ',', '');
        $yaz = fn (array $satir) => fputcsv($akis, $satir, ';', '"', '');

        $yaz(['Dönem', $r['baslangic']->format('d.m.Y').' – '.$r['bitis']->format('d.m.Y')]);
        $yaz(['Sipariş', $r['siparis']]);
        $yaz(['Satılan adet', $r['adet']]);
        $yaz(['Brüt ciro (KDV dahil)', $para($r['brut'])]);
        $yaz(['  bunun kargo ücreti', $para($r['kargo'])]);
        $yaz(['  uygulanan indirim', $para($r['indirim'])]);
        $yaz(['İade edilen', $para($r['iade'])]);
        $yaz(['Net ciro', $para($r['net'])]);
        $yaz([]);
        $yaz(['Gün', 'Sipariş', 'Ciro']);
        foreach ($r['gunluk'] as $g) {
            $yaz([CarbonImmutable::parse($g['gun'])->format('d.m.Y'), $g['siparis'], $para($g['ciro'])]);
        }
        $yaz([]);
        $yaz(['SKU', 'Ürün', 'Varyant', 'Adet', 'Ciro']);
        foreach ($r['cokSatan'] as $c) {
            $yaz([$c['sku'], $c['ad'], $c['varyant'], $c['adet'], $para($c['ciro'])]);
        }

        rewind($akis);
        $icerik = stream_get_contents($akis);
        fclose($akis);

        return $icerik;
    }
}
