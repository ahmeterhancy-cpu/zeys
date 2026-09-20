@extends('layout.vitrin')

@section('baslik', $belge->title . ' — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar yasal-sayfa">
    <h1>{{ $belge->title }}</h1>

    <p class="yasal-surum">
        Yürürlük sürümü: {{ $belge->version }}
        @if ($belge->published_at)
            · {{ $belge->published_at->translatedFormat('d F Y') }}
        @endif
    </p>

    {{--
        Metin panelden girilen güvenilir içeriktir (yalnızca yöneticiler
        yazabilir), bu yüzden HTML olarak basılır.
    --}}
    <div class="yasal-govde">{!! $belge->body !!}</div>
</div>
@endsection
