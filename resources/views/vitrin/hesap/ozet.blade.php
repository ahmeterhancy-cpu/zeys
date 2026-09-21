@extends('layout.vitrin')

@section('baslik', 'Hesabım — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar hesap-sayfa">

    <div class="hesap-basi">
        <div>
            <h1>Hesabım</h1>
            <p class="hesap-eposta">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="metin-dugme">Çıkış yap</button>
        </form>
    </div>

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif

    <nav class="hesap-menu">
        <a href="{{ route('account.index') }}" class="hesap-menu-aktif">Siparişlerim</a>
        <a href="{{ route('account.addresses') }}">Adreslerim</a>
        <a href="{{ route('account.data') }}">Verilerim</a>
    </nav>

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
@endsection
