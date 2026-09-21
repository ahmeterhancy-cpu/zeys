{{--
    Sayfa başlığı bandı — referanstaki gri şerit: ortada başlık, altında konum.
    @include('vitrin.parca.sayfa-basi', ['baslik' => 'Sepetim', 'konum' => ['Sepetim' => null]])
    `konum`: etiket => adres (son öğe null = bulunulan sayfa). Ana Sayfa kendiliğinden eklenir.
--}}
<div class="sayfa-basi">
    <div class="kap">
        <h1>{{ $baslik }}</h1>
        <nav class="iz" aria-label="Konum">
            <a href="{{ route('home') }}">Ana Sayfa</a>
            @foreach (($konum ?? [$baslik => null]) as $etiket => $adres)
                <span aria-hidden="true">/</span>
                @if ($adres)
                    <a href="{{ $adres }}">{{ $etiket }}</a>
                @else
                    <span aria-current="page">{{ $etiket }}</span>
                @endif
            @endforeach
        </nav>
    </div>
</div>
