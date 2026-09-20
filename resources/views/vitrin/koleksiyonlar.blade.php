@extends('layout.vitrin')

@section('baslik', 'Koleksiyonlar — ' . config('shop.ad'))
@section('aciklama', 'Zeys Fashion House koleksiyonları ve tüm parçalar.')

@section('icerik')
<div class="kap">

    @if ($koleksiyonlar->isNotEmpty())
        <section class="bolum bolum-ilk" style="padding-top:48px">
            <div class="bolum-basi">
                <h2>Koleksiyonlar</h2>
            </div>

            <div class="koleksiyon-izgara">
                @foreach ($koleksiyonlar as $koleksiyon)
                    <a class="koleksiyon-kart" href="{{ route('collections.show', $koleksiyon->slug) }}">
                        <div>
                            <h3>{{ $koleksiyon->name }}</h3>
                            @if ($koleksiyon->description)
                                <p>{{ $koleksiyon->description }}</p>
                            @endif
                            <span class="etiket">{{ $koleksiyon->products_count }} parça</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="bolum">
        <div class="bolum-basi">
            <h2>Tüm Parçalar</h2>
            <span class="etiket">{{ $urunler->total() }} ürün</span>
        </div>

        @if ($urunler->isEmpty())
            <div class="bos-durum">
                <p>Henüz ürün eklenmedi.</p>
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
