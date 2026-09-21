@extends('layout.vitrin')

@section('baslik', 'Mağaza — ' . config('shop.ad'))
@section('aciklama', 'Zeys Fashion House koleksiyonları ve tüm parçalar.')

@section('icerik')
    @include('vitrin.parca.sayfa-basi', ['baslik' => 'Mağaza', 'konum' => ['Tüm Ürünler' => null]])

    @if ($koleksiyonlar->isNotEmpty() && ! request()->hasAny(['sirala', 'fiyat_min', 'fiyat_max', 'page']))
        <section class="kap" style="margin-bottom:44px">
            <h2 class="gorunmez">Koleksiyonlar</h2>
            <div class="afisler afisler-{{ min(3, $koleksiyonlar->count()) }}">
                @foreach ($koleksiyonlar->take(3) as $koleksiyon)
                    <a class="afis {{ $koleksiyon->image ? 'afis-gorselli' : 'afis-duz afis-ton-' . $loop->iteration }}"
                       href="{{ route('collections.show', $koleksiyon->slug) }}"
                       @if ($koleksiyon->image) style="background-image: url('{{ asset('storage/' . $koleksiyon->image) }}')" @endif>
                        <span class="afis-metin">
                            <span class="afis-ust">{{ $koleksiyon->products_count }} parça</span>
                            <span class="afis-baslik">{{ $koleksiyon->name }}</span>
                            <span class="afis-bag">Koleksiyonu gör @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @include('vitrin.parca.katalog', [
        'urunler' => $urunler,
        'bosMesaj' => request()->hasAny(['fiyat_min', 'fiyat_max']) || request('sirala') === 'indirim'
            ? 'Bu süzgeçle eşleşen ürün yok.'
            : 'Henüz ürün eklenmedi.',
    ])
@endsection
