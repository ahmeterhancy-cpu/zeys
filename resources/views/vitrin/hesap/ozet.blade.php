@extends('layout.vitrin')

@section('baslik', 'Hesabım — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Hesabım', 'konum' => ['Hesabım' => route('account.index'), 'Hesabım' => null]])

<div class="kap hesap-duzen">
    @include('vitrin.parca.hesap-menu', ['aktif' => 'siparisler'])

<div class="hesap-icerik">

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif

    <h2>Siparişlerim</h2>

    @if ($siparisler->isEmpty())
        <div class="bos-durum">
            <p>Henüz siparişiniz yok.</p>
            <a class="dugme" href="{{ route('collections.index') }}">Koleksiyonları gör</a>
        </div>
    @else
        @foreach ($siparisler as $siparis)
            <a class="hesap-siparis" href="{{ route('account.order', $siparis->number) }}">
                <div>
                    <p class="hesap-siparis-no">{{ $siparis->number }}</p>
                    <p class="hesap-siparis-tarih">
                        {{ $siparis->created_at->format('d.m.Y') }} ·
                        {{ $siparis->items_count }} ürün
                    </p>
                </div>
                <div class="hesap-siparis-sag">
                    <span class="hesap-siparis-tutar">
                        {{ number_format((float) $siparis->grand_total, 2, ',', '.') }} TL
                    </span>
                    <span class="siparis-durum siparis-durum-{{ $siparis->status }}">
                        {{ $siparis->status_label }}
                    </span>
                </div>
            </a>
        @endforeach

        <div class="sayfalama">{{ $siparisler->links() }}</div>
    @endif
</div>
</div>
@endsection
