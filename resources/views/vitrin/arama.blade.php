@extends('layout.vitrin')

@section('baslik', ($sorgu ? $sorgu . ' — Arama' : 'Arama') . ' — ' . config('shop.ad'))

@section('icerik')
<div class="kap">
    <section class="bolum bolum-ilk" style="padding-top:44px">
        <div class="bolum-basi">
            <h2>Arama</h2>
            @if ($urunler)
                <span class="etiket">{{ $urunler->total() }} sonuç</span>
            @endif
        </div>

        <form method="GET" action="{{ route('catalog.search') }}" class="arama-formu">
            <label class="gorunmez" for="q">Ne arıyorsunuz?</label>
            <input type="search" id="q" name="q" value="{{ $sorgu }}"
                   placeholder="Ürün adı ya da SKU" class="metin-girdi">
            <button type="submit" class="dugme">Ara</button>
        </form>

        @if ($sorgu === '')
            <div class="bos-durum">
                <p>Aramak istediğiniz ürünün adını yazın.</p>
            </div>
        @elseif ($urunler->isEmpty())
            <div class="bos-durum">
                <p>“{{ $sorgu }}” için sonuç bulunamadı.</p>
                <a class="dugme dugme-cizgi" href="{{ route('collections.index') }}">Tüm parçalara bak</a>
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
