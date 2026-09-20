@extends('layout.vitrin')

@section('baslik', 'Sipariş ' . $order->number . ' — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar siparis-sayfa">

    <nav class="iz" aria-label="Konum">
        <a href="{{ route('account.index') }}">Hesabım</a>
        <span aria-hidden="true">/</span>
        <span>{{ $order->number }}</span>
    </nav>

    <div class="siparis-basi">
        <div>
            <h1>Sipariş {{ $order->number }}</h1>
            <p class="siparis-tarih">{{ $order->created_at->format('d.m.Y H:i') }}</p>
        </div>
        <span class="siparis-durum siparis-durum-{{ $order->status }}">{{ $order->status_label }}</span>
    </div>

    @if ($order->tracking_number)
        <div class="kargo-kutu">
            <span class="etiket">Kargo</span>
            <p>{{ $order->shipping_carrier }} · <strong>{{ $order->tracking_number }}</strong></p>
        </div>
    @endif

    <section class="bolum">
        <div class="bolum-basi"><h2>Ürünler</h2></div>

        @foreach ($order->items as $kalem)
            <div class="siparis-kalem">
                <div class="sepet-gorsel {{ $kalem->image ? '' : 'urun-gorsel-yok' }}">
                    @if ($kalem->image)
                        <img src="{{ asset('storage/' . $kalem->image) }}" alt="{{ $kalem->name }}">
                    @else
                        <span class="urun-harf" aria-hidden="true">Z</span>
                    @endif
                </div>
                <div class="sepet-bilgi">
                    <p class="sepet-ad">{{ $kalem->name }}</p>
                    @if ($kalem->variant_label)
                        <p class="sepet-varyant">{{ $kalem->variant_label }}</p>
                    @endif
                </div>
                <div class="sepet-tutar">
                    <span>× {{ $kalem->quantity }}</span>
                    <span>{{ number_format((float) $kalem->line_total, 2, ',', '.') }} TL</span>
                </div>
            </div>
        @endforeach

        <div class="siparis-toplam">
            <div class="ozet-satir ozet-toplam">
                <span>Toplam</span>
                <span>{{ number_format((float) $order->grand_total, 2, ',', '.') }} TL</span>
            </div>
        </div>
    </section>

    {{--
        İade talebi misafir akışındaki İMZALI sayfadan açılıyor.
        Buradan oraya bağlantı veriliyor ki tek bir uygulama olsun;
        iki ayrı iade formu iki ayrı doğrulama yolu demek olurdu.
    --}}
    @if ($order->payment_status === 'paid')
        <p style="margin-top:26px">
            <a class="dugme dugme-cizgi"
               href="{{ \Illuminate\Support\Facades\URL::signedRoute('order.show', ['order' => $order->number]) }}">
                İade / değişim talebi
            </a>
        </p>
    @endif
</div>
@endsection
