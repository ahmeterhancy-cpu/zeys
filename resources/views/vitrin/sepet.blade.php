@extends('layout.vitrin')

@section('baslik', 'Sepetim — ' . config('shop.ad'))

@section('icerik')
<div class="kap sepet-sayfa">

    <div class="bolum-basi">
        <h2>Sepetim</h2>
    </div>

    @if (session('bilgi'))
        <p class="uyari uyari-bilgi">{{ session('bilgi') }}</p>
    @endif
    @if (session('hata'))
        <p class="uyari uyari-hata">{{ session('hata') }}</p>
    @endif

    @if ($satirlar->isEmpty())
        <div class="bos-durum">
            <p>Sepetiniz henüz boş.</p>
            <a class="dugme" href="{{ route('collections.index') }}">Koleksiyonları gör</a>
        </div>
    @else
        @if ($satirlar->contains('adjusted', true))
            <p class="uyari uyari-hata">
                Sepetinizdeki bazı ürünlerin stoğu değişti. Aşağıda işaretli satırları
                gözden geçirip güncelleyin.
            </p>
        @endif

        <div class="sepet-duzen">
            <div class="sepet-satirlar">
                @foreach ($satirlar as $satir)
                    <div class="sepet-satir {{ $satir['adjusted'] ? 'sepet-satir-uyari' : '' }}">
                        <div class="sepet-gorsel {{ $satir['product']->hero_image ? '' : 'urun-gorsel-yok' }}">
                            @if ($satir['product']->hero_image)
                                <img src="{{ asset('storage/' . $satir['product']->hero_image) }}"
                                     alt="{{ $satir['product']->name }}">
                            @else
                                <span class="urun-harf" aria-hidden="true">Z</span>
                            @endif
                        </div>

                        <div class="sepet-bilgi">
                            <a class="sepet-ad" href="{{ route('products.show', $satir['product']->slug) }}">
                                {{ $satir['product']->name }}
                            </a>
                            <p class="sepet-varyant">{{ $satir['label'] }}</p>
                            <p class="sepet-sku">{{ $satir['variant']->sku }}</p>

                            @if ($satir['sold_out'])
                                <p class="sepet-uyari-metin">Bu beden tükendi — sepetten çıkarın.</p>
                            @elseif ($satir['adjusted'])
                                <p class="sepet-uyari-metin">
                                    Stok {{ $satir['quantity'] }} adete düştü
                                    (siz {{ $satir['requested'] }} adet istemiştiniz).
                                </p>
                            @endif
                        </div>

                        <div class="sepet-adet">
                            <form method="POST" action="{{ route('cart.update') }}">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $satir['variant']->id }}">
                                <label class="gorunmez" for="adet-{{ $satir['variant']->id }}">Adet</label>
                                <input type="number" id="adet-{{ $satir['variant']->id }}" name="quantity"
                                       value="{{ $satir['quantity'] }}" min="0"
                                       max="{{ max(1, $satir['variant']->available_stock) }}" class="adet-girdi">
                                <button type="submit" class="metin-dugme">Güncelle</button>
                            </form>
                        </div>

                        <div class="sepet-tutar">
                            <span>{{ number_format($satir['line_total'], 2, ',', '.') }} TL</span>
                            <form method="POST" action="{{ route('cart.remove') }}">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $satir['variant']->id }}">
                                <button type="submit" class="metin-dugme metin-dugme-sil">Çıkar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="sepet-ozet">
                <h3>Sipariş Özeti</h3>

                <div class="ozet-satir">
                    <span>Ara toplam</span>
                    <span>{{ number_format($araToplam, 2, ',', '.') }} TL</span>
                </div>

                @if ($indirim > 0)
                    <div class="ozet-satir ozet-indirim">
                        <span>İndirim{{ $kupon ? ' (' . $kupon->code . ')' : '' }}</span>
                        <span>-{{ number_format($indirim, 2, ',', '.') }} TL</span>
                    </div>
                @endif

                <div class="ozet-satir">
                    <span>Kargo</span>
                    <span>
                        @if ($kargo > 0)
                            {{ number_format($kargo, 2, ',', '.') }} TL
                        @else
                            Ücretsiz
                        @endif
                    </span>
                </div>

                @if ($kalanKargo > 0)
                    <p class="ozet-tesvik">
                        Ücretsiz kargoya {{ number_format($kalanKargo, 2, ',', '.') }} TL kaldı.
                    </p>
                @endif

                <div class="ozet-satir ozet-toplam">
                    <span>Toplam</span>
                    <span>{{ number_format($toplam, 2, ',', '.') }} TL</span>
                </div>

                @if ($kupon)
                    <form method="POST" action="{{ route('cart.coupon.forget') }}" class="kupon-formu">
                        @csrf
                        <button type="submit" class="metin-dugme">Kuponu kaldır</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('cart.coupon') }}" class="kupon-formu">
                        @csrf
                        <label class="gorunmez" for="kupon">Kupon kodu</label>
                        <input type="text" id="kupon" name="code" placeholder="Kupon kodu" class="metin-girdi">
                        <button type="submit" class="dugme dugme-cizgi">Uygula</button>
                    </form>
                @endif

                <a class="dugme sepet-odeme" href="{{ route('checkout.form') }}">Ödemeye geç</a>
            </aside>
        </div>
    @endif
</div>
@endsection
