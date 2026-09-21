{{--
    Ürün kartı — PressMart kartı: rozetler, üzerine gelince ikinci görsel,
    alt şeritte "İncele", altında koleksiyon, ad, puan, fiyat, renkler.
    Sorgu Product::kartIcin() kapsamıyla gelmeli (bkz. model).
    Fotoğraf yoksa boş kutu değil, markanın harfini taşıyan yüzey.
--}}
@php
    $urunAdresi = url('/urun/' . $urun->slug);
    $ikinci = $urun->ikinci_gorsel;
@endphp
<article class="urun-kart">
    <div class="urun-gorsel {{ $urun->hero_image ? '' : 'urun-gorsel-yok' }}">
        <a href="{{ $urunAdresi }}" class="urun-gorsel-bag" tabindex="-1" aria-hidden="true">
            @if ($urun->hero_image)
                <img src="{{ asset('storage/' . $urun->hero_image) }}" alt="" loading="lazy" class="urun-gorsel-on">
                @if ($ikinci)
                    <img src="{{ asset('storage/' . $ikinci) }}" alt="" loading="lazy" class="urun-gorsel-arka">
                @endif
            @else
                <span class="urun-harf">Z</span>
            @endif
        </a>

        <div class="urun-rozetler">
            @if ($urun->indirim_orani)
                <span class="rozet rozet-indirim">%{{ $urun->indirim_orani }} İndirim</span>
            @endif
            @if ($urun->badge)
                <span class="rozet rozet-yeni urun-rozet">{{ $urun->badge }}</span>
            @endif
            @if ($urun->total_stock < 1)
                <span class="rozet rozet-tukendi">Tükendi</span>
            @endif
        </div>

        <a href="{{ $urunAdresi }}" class="urun-incele" tabindex="-1" aria-hidden="true">
            @include('vitrin.parca.ikon', ['ad' => 'goz']) Hızlı incele
        </a>
    </div>

    <div class="urun-bilgi">
        @if ($urun->relationLoaded('collection') && $urun->collection)
            <p class="urun-kat">{{ $urun->collection->name }}</p>
        @endif

        <h3 class="urun-ad"><a href="{{ $urunAdresi }}">{{ $urun->name }}</a></h3>

        @if ($urun->review_count > 0)
            <p class="urun-puan" aria-label="{{ number_format((float) $urun->rating, 1, ',', '') }} / 5, {{ $urun->review_count }} değerlendirme">
                <span class="yildiz" aria-hidden="true">{{ str_repeat('★', (int) round((float) $urun->rating)) }}<span class="yildiz-bos">{{ str_repeat('★', 5 - (int) round((float) $urun->rating)) }}</span></span>
                <span aria-hidden="true">({{ $urun->review_count }})</span>
            </p>
        @endif

        <p class="urun-fiyat">
            <strong>
                @if ($urun->has_price_range)
                    {{ number_format((float) $urun->min_price, 2, ',', '.') }} – {{ number_format((float) $urun->max_price, 2, ',', '.') }} TL
                @else
                    {{ number_format((float) $urun->min_price, 2, ',', '.') }} TL
                @endif
            </strong>
            @if ($urun->kart_eski_fiyat)
                <del>{{ number_format($urun->kart_eski_fiyat, 2, ',', '.') }} TL</del>
            @endif
        </p>

        @if ($urun->renkler->isNotEmpty())
            <div class="urun-renkler" aria-label="Renkler: {{ $urun->renkler->pluck('value')->implode(', ') }}">
                @foreach ($urun->renkler as $renk)
                    <span class="urun-renk" style="background: {{ $renk->color_hex ?: 'transparent' }}"
                          title="{{ $renk->value }}"></span>
                @endforeach
            </div>
        @endif

        @if ($urun->total_stock < 1)
            <a href="{{ $urunAdresi }}" class="dugme-kucuk" aria-label="{{ $urun->name }} — stoğa girince haber ver">Gelince haber ver</a>
        @else
            <a href="{{ $urunAdresi }}" class="dugme-kucuk" aria-label="{{ $urun->name }} — seçenekleri gör">Seçenekleri gör</a>
        @endif
    </div>
</article>
