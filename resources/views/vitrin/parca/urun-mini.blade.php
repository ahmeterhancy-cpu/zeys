{{-- Küçük ürün satırı — referanstaki "Featured / Best Selling" listeleri --}}
<a class="urun-mini" href="{{ url('/urun/' . $urun->slug) }}">
    <span class="urun-mini-gorsel {{ $urun->hero_image ? '' : 'urun-gorsel-yok' }}">
        @if ($urun->hero_image)
            <img src="{{ asset('storage/' . $urun->hero_image) }}" alt="" loading="lazy">
        @else
            <span class="urun-harf" aria-hidden="true">Z</span>
        @endif
    </span>
    <span class="urun-mini-bilgi">
        <span class="urun-mini-ad">{{ $urun->name }}</span>
        @if ($urun->review_count > 0)
            <span class="yildiz" aria-label="{{ number_format((float) $urun->rating, 1, ',', '') }} / 5">{{ str_repeat('★', (int) round((float) $urun->rating)) }}<span class="yildiz-bos">{{ str_repeat('★', 5 - (int) round((float) $urun->rating)) }}</span></span>
        @endif
        <span class="urun-fiyat">
            <strong>{{ number_format((float) $urun->min_price, 2, ',', '.') }} TL</strong>
            @if ($urun->kart_eski_fiyat)
                <del>{{ number_format($urun->kart_eski_fiyat, 2, ',', '.') }} TL</del>
            @endif
        </span>
    </span>
</a>
