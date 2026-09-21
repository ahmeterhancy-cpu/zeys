@extends('layout.vitrin')

@section('baslik', ($sorgu ? $sorgu . ' — Arama' : 'Arama') . ' — ' . config('shop.ad'))

@section('katalog_ust')
    <form method="GET" action="{{ route('catalog.search') }}" class="arama-formu">
        <label class="gorunmez" for="q">Ne arıyorsunuz?</label>
        <input type="search" id="q" name="q" value="{{ $sorgu }}"
               placeholder="Ürün adı ya da SKU" class="metin-girdi">
        @if ($kategori)
            <input type="hidden" name="kategori" value="{{ $kategori->slug }}">
        @endif
        <button type="submit" class="dugme">@include('vitrin.parca.ikon', ['ad' => 'arama']) Ara</button>
    </form>

    @if ($sorgu === '')
        <div class="bos-durum">
            <p>Aramak istediğiniz ürünün adını yazın.</p>
        </div>
    @elseif ($urunler->isNotEmpty())
        <p class="arama-ozet">
            “{{ $sorgu }}”{{ $kategori ? ' · ' . $kategori->name . ' kategorisinde' : '' }} için {{ $urunler->total() }} sonuç
        </p>
    @endif
@endsection

@section('icerik')
    @include('vitrin.parca.sayfa-basi', [
        'baslik' => $sorgu ? '“' . $sorgu . '” için sonuçlar' : 'Arama',
        'konum' => ['Arama' => null],
    ])

    @include('vitrin.parca.katalog', [
        'urunler' => $urunler,
        'aktifKategori' => $kategori,
        'bosMesaj' => '“' . $sorgu . '” için sonuç bulunamadı.',
    ])
@endsection
