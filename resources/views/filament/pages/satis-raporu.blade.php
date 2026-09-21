{{--
    Panel temasında özel Tailwind derlemesi yok: panelin hazır CSS'inde
    bulunmayan sınıflar etkisiz kalır. Bu yüzden düzen satır içi stil ve
    bu dosyadaki küçük <style> ile kuruldu; renkler panelin kendi
    değişkenlerinden (--primary-*) geliyor, koyu temada da okunur.
--}}
@php
    $r = $this->rapor;
    $para = fn ($t) => number_format((float) $t, 2, ',', '.').' TL';
    $enYuksek = max(1, collect($r['gunluk'])->max('ciro'));
@endphp

<x-filament-panels::page>
    <style>
        .zr-izgara { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 12px; }
        .zr-kart { border: 1px solid rgba(127,127,127,.22); border-radius: 12px; padding: 14px 16px; }
        .zr-kart small { display: block; opacity: .7; font-size: 12px; letter-spacing: .02em; }
        .zr-kart strong { display: block; font-size: 20px; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .zr-kart span { display: block; font-size: 12px; opacity: .65; margin-top: 2px; }
        .zr-kart--net { border-color: var(--primary-500); }
        .zr-aralik { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .zr-aralik button { border: 1px solid rgba(127,127,127,.3); border-radius: 999px; padding: 4px 12px; font-size: 13px; }
        .zr-aralik button:hover { border-color: var(--primary-500); }
        .zr-tablo { width: 100%; border-collapse: collapse; font-size: 14px; }
        .zr-tablo th { text-align: left; font-weight: 600; font-size: 12px; opacity: .7; padding: 8px 10px; border-bottom: 1px solid rgba(127,127,127,.25); }
        .zr-tablo td { padding: 8px 10px; border-bottom: 1px solid rgba(127,127,127,.12); font-variant-numeric: tabular-nums; }
        .zr-tablo .sag { text-align: right; }
        .zr-cubuk { height: 8px; border-radius: 4px; background: var(--primary-500); min-width: 2px; }
        .zr-kaydir { overflow-x: auto; }
    </style>

    <x-filament::section>
        {{ $this->form }}

        <div class="zr-aralik">
            @foreach (['bugun' => 'Bugün', 'dun' => 'Dün', '7gun' => 'Son 7 gün', 'buay' => 'Bu ay', 'gecenay' => 'Geçen ay', 'buyil' => 'Bu yıl'] as $anahtar => $etiket)
                <button type="button" wire:click="aralik('{{ $anahtar }}')">{{ $etiket }}</button>
            @endforeach
        </div>
    </x-filament::section>

    <div class="zr-izgara">
        <div class="zr-kart">
            <small>Sipariş</small>
            <strong>{{ $r['siparis'] }}</strong>
            <span>{{ $r['adet'] }} parça ürün</span>
        </div>
        <div class="zr-kart">
            <small>Brüt ciro</small>
            <strong>{{ $para($r['brut']) }}</strong>
            <span>kargo {{ $para($r['kargo']) }} dahil</span>
        </div>
        <div class="zr-kart">
            <small>İade edilen</small>
            <strong>{{ $para($r['iade']) }}</strong>
            <span>{{ $r['iptal'] }} sipariş iptal</span>
        </div>
        <div class="zr-kart zr-kart--net">
            <small>Net ciro</small>
            <strong>{{ $para($r['net']) }}</strong>
            <span>KDV dahil</span>
        </div>
        <div class="zr-kart">
            <small>Ortalama sepet</small>
            <strong>{{ $para($r['ortalama']) }}</strong>
            <span>indirim toplamı {{ $para($r['indirim']) }}</span>
        </div>
    </div>

    <x-filament::section heading="En çok satan varyantlar">
        @if ($r['cokSatan'] === [])
            <p style="opacity:.7">Bu aralıkta ödenmiş sipariş yok.</p>
        @else
            <div class="zr-kaydir">
                <table class="zr-tablo">
                    <thead>
                        <tr><th>Ürün</th><th>Varyant</th><th>SKU</th><th class="sag">Adet</th><th class="sag">Ciro</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($r['cokSatan'] as $c)
                            <tr>
                                <td>{{ $c['ad'] }}</td>
                                <td>{{ $c['varyant'] ?? '—' }}</td>
                                <td style="opacity:.7">{{ $c['sku'] ?? '—' }}</td>
                                <td class="sag">{{ $c['adet'] }}</td>
                                <td class="sag">{{ $para($c['ciro']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Gün gün">
        @if ($r['gunluk'] === [])
            <p style="opacity:.7">Günlük döküm en fazla 93 günlük aralıkta gösterilir; CSV'de özet yer alır.</p>
        @else
            <div class="zr-kaydir">
                <table class="zr-tablo">
                    <thead>
                        <tr><th>Gün</th><th class="sag">Sipariş</th><th class="sag">Ciro</th><th style="width:40%"></th></tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($r['gunluk']) as $g)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($g['gun'])->translatedFormat('d M, D') }}</td>
                                <td class="sag">{{ $g['siparis'] }}</td>
                                <td class="sag">{{ $para($g['ciro']) }}</td>
                                <td>
                                    @if ($g['ciro'] > 0)
                                        <div class="zr-cubuk" style="width: {{ round($g['ciro'] / $enYuksek * 100, 1) }}%"></div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <p style="font-size:12px;opacity:.65">
        Dönem, siparişin ödendiği güne göre sayılır; ödemesi alınmamış siparişler dahil değildir.
        İade sütunu, bu dönemde ödenen siparişlerden bugüne dek geri ödenen tutardır.
    </p>
</x-filament-panels::page>
