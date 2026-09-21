@extends('layout.vitrin')

@section('baslik', $kategori->name . ' — ' . config('shop.ad'))
@section('aciklama', $kategori->meta_description ?: ($kategori->description ?: $kategori->name . ' koleksiyonu.'))

@section('icerik')
    @include('vitrin.parca.sayfa-basi', [
        'baslik' => $kategori->name,
        'konum' => array_filter([
            'Mağaza' => route('collections.index'),
            $kategori->parent?->name => $kategori->parent ? route('catalog.category', $kategori->parent->slug) : null,
            $kategori->name => null,
        ], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH),
    ])

    @include('vitrin.parca.katalog', [
        'urunler' => $urunler,
        'aktifKategori' => $kategori,
        'altKategoriler' => $kategori->children->where('is_active', true),
        'aciklama' => $kategori->description,
        'bosMesaj' => 'Bu kategoride henüz ürün yok.',
    ])
@endsection
