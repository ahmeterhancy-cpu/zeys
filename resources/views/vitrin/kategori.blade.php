@extends('layout.vitrin')

@section('baslik', $kategori->name . ' — ' . config('shop.ad'))
@section('aciklama', $kategori->meta_description ?: ($kategori->description ?: $kategori->name . ' koleksiyonu.'))

@section('icerik')
<div class="kap">
    <nav class="iz" aria-label="Konum">
        <a href="{{ route('home') }}">Ana Sayfa</a>
        <span aria-hidden="true">/</span>
        @if ($kategori->parent)
            <a href="{{ route('catalog.category', $kategori->parent->slug) }}">{{ $kategori->parent->name }}</a>
            <span aria-hidden="true">/</span>
        @endif
        <span>{{ $kategori->name }}</span>
    </nav>

    <section class="bolum bolum-ilk">
        <div class="bolum-basi">
            <h2>{{ $kategori->name }}</h2>
            <span class="etiket">{{ $urunler->total() }} parça</span>
        </div>

        @if ($kategori->description)
            <p class="koleksiyon-ozet">{{ $kategori->description }}</p>
        @endif

        @if ($kategori->children->isNotEmpty())
            <div class="alt-kategoriler">
                @foreach ($kategori->children as $alt)
                    <a href="{{ route('catalog.category', $alt->slug) }}">{{ $alt->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($urunler->isEmpty())
            <div class="bos-durum">
                <p>Bu kategoride henüz ürün yok.</p>
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
