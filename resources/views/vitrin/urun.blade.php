@extends('layout.vitrin')

@section('baslik', $urun->name . ' — ' . config('shop.ad'))
@section('aciklama', $urun->short_description ?: $urun->name)

@section('og_tur', 'product')
@if ($urun->hero_image)
    @section('og_gorsel', asset('storage/' . $urun->hero_image))
@endif

@push('yapisal_veri')
    @include('vitrin.parca.jsonld', ['tur' => 'urun', 'urun' => $urun, 'yorumlar' => $yorumlar])
@endpush

@section('icerik')
@php
    // TUZAK: @json(...) içine parantezli/çok satırlı ifade yazılmaz.
    // Değerler burada hazırlanıp aşağıda tek değişken olarak veriliyor.
    $varyantVerisi = $varyantlar->all();
    $galeriVerisi = $renkGalerisi->all();
    $ilkGorsel = $genelYollar->first();
@endphp

{{-- Ürün sayfasında gri bant yok (referansta da yok): ince konum satırı --}}
<div class="kap">
    <nav class="iz iz-sol" aria-label="Konum">
        <a href="{{ route('home') }}">Ana Sayfa</a>
        <span aria-hidden="true">/</span>
        @if ($urun->collection)
            <a href="{{ route('collections.show', $urun->collection->slug) }}">{{ $urun->collection->name }}</a>
        @else
            <a href="{{ route('collections.index') }}">Mağaza</a>
        @endif
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $urun->name }}</span>
    </nav>
</div>

<div class="kap urun-sayfa">

    <div class="urun-duzen">

        {{-- Galeri: solda küçük görseller, sağda büyük görsel --}}
        <div class="urun-galeri {{ $genelYollar->count() > 1 ? 'urun-galeri-coklu' : '' }}" id="galeri">
            <div class="urun-galeri-ana {{ $ilkGorsel ? '' : 'urun-gorsel-yok' }}">
                @if ($ilkGorsel)
                    {{--
                        data-taban: renk degisiminde JS yeni yolu bunun ustune ekler.
                        Onceden src icindeki "/storage/" parcasi regex'le degistiriliyordu;
                        CDN ya da farkli disk yolunda sessizce bozulurdu.
                    --}}
                    <img id="galeri-ana"
                         src="{{ asset('storage/' . $ilkGorsel) }}"
                         data-taban="{{ rtrim(asset('storage'), '/') }}/"
                         data-ilk="{{ asset('storage/' . $ilkGorsel) }}"
                         alt="{{ $urun->name }}">
                @else
                    <span class="urun-harf" aria-hidden="true">Z</span>
                @endif

                <div class="urun-rozetler">
                    @if ($urun->badge)
                        <span class="rozet rozet-yeni">{{ $urun->badge }}</span>
                    @endif
                </div>
            </div>

            @if ($genelYollar->count() > 1)
                <div class="urun-galeri-kucuk">
                    @foreach ($genelYollar as $yol)
                        <button type="button" class="galeri-kucuk-dugme"
                                data-gorsel="{{ asset('storage/' . $yol) }}">
                            <img src="{{ asset('storage/' . $yol) }}"
                                 alt="{{ $urun->name }} görsel {{ $loop->iteration }}" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Özet ve seçim --}}
        <div class="urun-panel">
            <h1>{{ $urun->name }}</h1>

            <div class="urun-panel-ust">
                @if ($urun->review_count > 0)
                    <a href="#urun-sekmeler" class="puan-ozet" data-sekme-ac="sekme-yorumlar">
                        <span class="yildiz" aria-hidden="true">{{ str_repeat('★', (int) round((float) $urun->rating)) }}<span class="yildiz-bos">{{ str_repeat('★', 5 - (int) round((float) $urun->rating)) }}</span></span>
                        <span>{{ number_format((float) $urun->rating, 1, ',', '') }} / 5 · {{ $urun->review_count }} değerlendirme</span>
                    </a>
                @endif
                @if ($urun->base_sku)
                    <span class="urun-panel-sku">Ürün kodu: {{ $urun->base_sku }}</span>
                @endif
            </div>

            <p class="urun-panel-fiyat" id="secim-fiyat">
                @if ($urun->has_price_range)
                    {{ number_format((float) $urun->min_price, 2, ',', '.') }} –
                    {{ number_format((float) $urun->max_price, 2, ',', '.') }} TL
                @else
                    {{ number_format((float) $urun->min_price, 2, ',', '.') }} TL
                @endif
            </p>

            @if ($urun->short_description)
                <p class="urun-panel-ozet">{{ $urun->short_description }}</p>
            @endif

            @if (session('hata'))
                <p class="uyari uyari-hata">{{ session('hata') }}</p>
            @endif

            <form method="POST" action="{{ route('cart.add') }}" id="sepet-formu">
                @csrf
                <input type="hidden" name="variant_id" id="variant-id" value="">

                @foreach ($urun->options as $eksen)
                    <fieldset class="eksen" data-eksen="{{ $eksen->id }}">
                        <legend class="eksen-baslik">
                            <span>{{ $eksen->name }}</span>
                            {{--
                                Beden tablosu dugmesi ilk METIN ekseninde gosteriliyor.
                                Onceden eksen adi tam olarak "Beden" olmak zorundaydi;
                                magaza ekseni "Olcu" ya da "Numara" diye adlandirsa
                                tablo hic gorunmuyordu.
                            --}}
                            @if ($bedenTablosu && ! $eksen->is_color && $loop->first)
                                <button type="button" class="beden-tablosu-ac">Beden tablosu</button>
                            @endif
                        </legend>

                        <div class="eksen-secenekler {{ $eksen->is_color ? 'eksen-renk' : '' }}">
                            @foreach ($eksen->values as $deger)
                                <label class="secenek {{ $eksen->is_color ? 'secenek-renk' : '' }}">
                                    <input type="radio" name="eksen_{{ $eksen->id }}"
                                           value="{{ $deger->id }}" class="gorunmez eksen-girdi">
                                    @if ($eksen->is_color)
                                        <span class="secenek-nokta"
                                              style="background: {{ $deger->color_hex ?: 'transparent' }}"></span>
                                    @endif
                                    <span class="secenek-ad">{{ $deger->value }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <p class="secim-durum" id="secim-durum" role="status"></p>

                <div class="sepete-satir">
                    <label class="gorunmez" for="adet">Adet</label>
                    <input type="number" name="quantity" id="adet" value="1" min="1" max="20" class="adet-girdi">
                    <button type="submit" class="dugme sepete-dugme" id="sepete-dugme" disabled>
                        Seçim yapın
                    </button>
                </div>
            </form>

            {{--
                "Stokta yok — haber ver".
                JS, secilen varyant tukenmisse bu bolumu aciyor ve gizli
                alana varyant kimligini yaziyor. JS calismazsa bolum
                kapali kalir ama sayfanin geri kalani calisir.
            --}}
            <div class="haber-ver" id="haber-ver" hidden>
                <p class="haber-ver-baslik">Bu beden tükendi</p>
                <p class="haber-ver-metin">
                    Yeniden stoğa girdiğinde size haber verelim.
                </p>

                <form method="POST" action="{{ route('stock.inquiry') }}" class="haber-ver-formu">
                    @csrf
                    <input type="hidden" name="variant_id" id="haber-ver-varyant" value="">
                    <label class="gorunmez" for="haber-ver-eposta">E-posta</label>
                    <input type="email" id="haber-ver-eposta" name="eposta"
                           placeholder="E-posta adresiniz" class="metin-girdi" required>
                    <button type="submit" class="dugme dugme-cizgi">Haber ver</button>
                </form>
            </div>

            @if (session('bilgi'))
                <p class="uyari">{{ session('bilgi') }}</p>
            @endif

            <ul class="urun-meta">
                @if ($urun->collection)
                    <li><span>Koleksiyon:</span> <a href="{{ route('collections.show', $urun->collection->slug) }}">{{ $urun->collection->name }}</a></li>
                @endif
                @if ($urun->categories->isNotEmpty())
                    <li><span>Kategori:</span>
                        @foreach ($urun->categories as $kat)
                            <a href="{{ route('catalog.category', $kat->slug) }}">{{ $kat->name }}</a>@if (! $loop->last),@endif
                        @endforeach
                    </li>
                @endif
            </ul>

            <ul class="urun-guvence">
                <li>@include('vitrin.parca.ikon', ['ad' => 'kamyon'])
                    @if ((float) config('shop.kargo.ucretsiz_esigi') > 0)
                        {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL üzeri kargo ücretsiz
                    @else
                        Türkiye geneli kargo
                    @endif
                </li>
                <li>@include('vitrin.parca.ikon', ['ad' => 'iade']) Teslimden sonra {{ config('shop.cayma_hakki_gun') }} gün iade hakkı</li>
                <li>@include('vitrin.parca.ikon', ['ad' => 'kalkan']) PayTR ile güvenli ödeme</li>
            </ul>
        </div>
    </div>

    {{-- Sekmeler: Açıklama / Ek bilgi / Değerlendirmeler --}}
    <section class="urun-sekmeler bolum" id="urun-sekmeler" data-sekmeler>
        <div class="sekmeler" role="tablist" aria-label="Ürün bilgileri">
            <button type="button" class="sekme" role="tab" id="sekme-aciklama" aria-controls="panel-aciklama" aria-selected="true">Açıklama</button>
            <button type="button" class="sekme" role="tab" id="sekme-bilgi" aria-controls="panel-bilgi" aria-selected="false">Ek Bilgi</button>
            <button type="button" class="sekme" role="tab" id="sekme-yorumlar" aria-controls="panel-yorumlar" aria-selected="false">Değerlendirmeler ({{ $urun->review_count }})</button>
        </div>

        <div class="sekme-panel" role="tabpanel" id="panel-aciklama" aria-labelledby="sekme-aciklama">
            @if ($urun->description)
                <div class="urun-aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @else
                <p class="urun-aciklama">{{ $urun->short_description ?: 'Bu ürün için ayrıntılı açıklama henüz eklenmedi.' }}</p>
            @endif
        </div>

        <div class="sekme-panel" role="tabpanel" id="panel-bilgi" aria-labelledby="sekme-bilgi">
            <table class="urun-detay">
                <tbody>
                    @foreach ($urun->options as $eksen)
                        <tr><th scope="row">{{ $eksen->name }}</th><td>{{ $eksen->values->pluck('value')->implode(', ') }}</td></tr>
                    @endforeach
                    @if ($urun->material)
                        <tr><th scope="row">Kumaş</th><td>{{ $urun->material }}</td></tr>
                    @endif
                    @if ($urun->model_note)
                        <tr><th scope="row">Model ölçüsü</th><td>{{ $urun->model_note }}</td></tr>
                    @endif
                    @if ($urun->care_notes)
                        <tr><th scope="row">Bakım</th><td>{{ $urun->care_notes }}</td></tr>
                    @endif
                    <tr>
                        <th scope="row">Kargo ve iade</th>
                        <td>
                            {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL üzeri kargo ücretsiz.
                            Teslimden sonra {{ config('shop.cayma_hakki_gun') }} gün iade hakkınız var.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="sekme-panel yorumlar" role="tabpanel" id="panel-yorumlar" aria-labelledby="sekme-yorumlar">
            <div id="yorumlar"></div>
            @if ($yorumlar->isNotEmpty())
                <p class="yorumlar-ozet">
                    <strong>{{ number_format((float) $urun->rating, 1, ',', '') }}</strong> / 5
                    · {{ $urun->review_count }} değerlendirme
                </p>

                <ul class="yorum-liste">
                    @foreach ($yorumlar as $yorum)
                        <li class="yorum">
                            <p class="yorum-ust">
                                <span class="yildiz" aria-label="{{ $yorum->rating }} / 5">{{ str_repeat('★', $yorum->rating) }}<span class="yildiz-bos">{{ str_repeat('★', 5 - $yorum->rating) }}</span></span>
                                @if ($yorum->title)
                                    <strong>{{ $yorum->title }}</strong>
                                @endif
                            </p>
                            <p class="yorum-metin">{!! nl2br(e($yorum->body)) !!}</p>
                            <p class="yorum-alt">
                                {{ $yorum->author_name }} · {{ ($yorum->approved_at ?? $yorum->created_at)->translatedFormat('d F Y') }}
                                · <span class="yorum-dogrulandi">Satın aldı</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p>Bu ürün için henüz değerlendirme yok.</p>
            @endif
            <p class="yorum-not">
                Değerlendirmeyi yalnız ürünü satın alan müşterilerimiz, teslimattan sonra sipariş sayfalarından yazabilir.
            </p>
        </div>
    </section>

    @if ($bedenTablosu)
        <dialog class="beden-tablosu" id="beden-tablosu">
            <div class="beden-tablosu-ic">
                <div class="beden-tablosu-ust">
                    <h2>{{ $bedenTablosu->name }} — Beden Tablosu</h2>
                    <button type="button" class="beden-tablosu-kapat" aria-label="Kapat">&times;</button>
                </div>

                <table>
                    <thead>
                        <tr>
                            @foreach ($bedenTablosu->columns as $sutun)
                                <th scope="col">{{ $sutun }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bedenTablosu->rows as $satir)
                            <tr>
                                @foreach ($satir as $hucre)
                                    <td>{{ $hucre }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($bedenTablosu->note)
                    <p class="beden-tablosu-not">{{ $bedenTablosu->note }}</p>
                @endif
            </div>
        </dialog>
    @endif

    @if ($urun->related->isNotEmpty())
        <section class="bolum">
            <div class="bolum-baslik"><h2>Birlikte kullanın</h2></div>
            @include('vitrin.parca.urun-serit', ['urunler' => $urun->related])
        </section>
    @endif

    @if ($benzerler->isNotEmpty())
        <section class="bolum">
            <div class="bolum-baslik"><h2>Benzer ürünler</h2></div>
            @include('vitrin.parca.urun-serit', ['urunler' => $benzerler])
        </section>
    @endif
</div>

@push('betik')
    @vite(['resources/js/urun.js'])
@endpush

<script type="application/json" id="varyant-verisi">@json($varyantVerisi)</script>
<script type="application/json" id="galeri-verisi">@json($galeriVerisi)</script>
@endsection
