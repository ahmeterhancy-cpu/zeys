{{-- Ürün kartı. Fotoğraf yoksa boş kutu değil, markanın harfini taşıyan kâğıt yüzey. --}}
<a class="urun-kart" href="{{ url('/urun/' . $urun->slug) }}">
    <div class="urun-gorsel {{ $urun->hero_image ? '' : 'urun-gorsel-yok' }}">
        @if ($urun->hero_image)
            <img src="{{ asset('storage/' . $urun->hero_image) }}"
                 alt="{{ $urun->name }}" loading="lazy">
        @else
            <span class="urun-harf" aria-hidden="true">Z</span>
        @endif

        @if ($urun->badge)
            <span class="urun-rozet">{{ $urun->badge }}</span>
        @endif
    </div>

    <div class="urun-bilgi">
        <h3 class="urun-ad">{{ $urun->name }}</h3>

        <p class="urun-fiyat">
            @if ($urun->has_price_range)
                {{ number_format((float) $urun->min_price, 2, ',', '.') }} –
                {{ number_format((float) $urun->max_price, 2, ',', '.') }} TL
            @else
                {{ number_format((float) $urun->min_price, 2, ',', '.') }} TL
            @endif
        </p>

        @if ($urun->renkler->isNotEmpty())
            <div class="urun-renkler" aria-hidden="true">
                @foreach ($urun->renkler as $renk)
                    <span class="urun-renk" style="background: {{ $renk->color_hex ?: 'transparent' }}"
                          title="{{ $renk->value }}"></span>
                @endforeach
            </div>
        @endif

        @if ($urun->total_stock < 1)
            <p class="urun-tukendi">Tükendi</p>
        @endif
    </div>
</a>
