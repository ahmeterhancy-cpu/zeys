@extends('layout.vitrin')

@section('baslik', $koleksiyon->name . ' — ' . config('shop.ad'))
@section('aciklama', $koleksiyon->description ?: $koleksiyon->name . ' koleksiyonu.')

@section('icerik')
<div class="kap">
    <nav class="iz" aria-label="Konum">
        <a href="{{ route('home') }}">Ana Sayfa</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('collections.index') }}">Koleksiyonlar</a>
        <span aria-hidden="true">/</span>
        <span>{{ $koleksiyon->name }}</span>
    </nav>

    <section class="bolum bolum-ilk">
        <div class="bolum-basi">
            <h2>{{ $koleksiyon->name }}</h2>
            <span class="etiket">{{ $urunler->total() }} parça</span>
        </div>

        @if ($koleksiyon->description)
            <p class="koleksiyon-ozet">{{ $koleksiyon->description }}</p>
        @endif

        @if ($urunler->isEmpty())
            <div class="bos-durum">
                <p>Bu koleksiyonda henüz ürün yok.</p>
                <a class="dugme dugme-cizgi" href="{{ route('collections.index') }}">Tüm parçalar</a>
            </div>
        @else
            <div class="urun-izgara">
                @foreach ($urunler as $urun)
                    @include('vitrin.parca.urun-kart', ['urun' => $urun])
                @endforeach
            </div>

            <div class="sayfalama">{{ $urunler->links() }}</div>
        @endif
    </section>
</div>
@endsection
