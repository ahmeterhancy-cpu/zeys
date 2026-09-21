@extends('layout.vitrin')

@section('baslik', $koleksiyon->name . ' — ' . config('shop.ad'))
@section('aciklama', $koleksiyon->description ?: $koleksiyon->name . ' koleksiyonu.')

@section('icerik')
    @include('vitrin.parca.sayfa-basi', [
        'baslik' => $koleksiyon->name,
        'konum' => ['Koleksiyonlar' => route('collections.index'), $koleksiyon->name => null],
    ])

    @include('vitrin.parca.katalog', [
        'urunler' => $urunler,
        'aktifKoleksiyon' => $koleksiyon,
        'aciklama' => $koleksiyon->description,
        'bosMesaj' => 'Bu koleksiyonda henüz ürün yok.',
    ])
@endsection
