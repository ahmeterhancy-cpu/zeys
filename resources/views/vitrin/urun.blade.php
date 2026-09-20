@extends('layout.vitrin')

@section('baslik', $urun->name . ' — ' . config('shop.ad'))
@section('aciklama', $urun->short_description ?: $urun->name)

@section('icerik')
@php
    // TUZAK: @json(...) içine parantezli/çok satırlı ifade yazılmaz.
    // Değerler burada hazırlanıp aşağıda tek değişken olarak veriliyor.
    $varyantVerisi = $varyantlar->all();
    $galeriVerisi = $renkGalerisi->all();
    $ilkGorsel = $genelGorseller->first();
@endphp

<div class="kap urun-sayfa">

    <nav class="iz" aria-label="Konum">
        <a href="{{ route('home') }}">Ana Sayfa</a>
        <span aria-hidden="true">/</span>
        @if ($urun->collection)
            <a href="{{ route('collections.show', $urun->collection->slug) }}">{{ $urun->collection->name }}</a>
            <span aria-hidden="true">/</span>
        @endif
        <span>{{ $urun->name }}</span>
    </nav>

    <div class="urun-duzen">

        {{-- Galeri --}}
        <div class="urun-galeri" id="galeri">
            <div class="urun-galeri-ana {{ $ilkGorsel ? '' : 'urun-gorsel-yok' }}">
                @if ($ilkGorsel)
                    <img id="galeri-ana" src="{{ asset('storage/' . $ilkGorsel->path) }}"
                         alt="{{ $urun->name }}">
                @else
                    <span class="urun-harf" aria-hidden="true">Z</span>
                @endif
            </div>

            @if ($genelGorseller->count() > 1)
                <div class="urun-galeri-kucuk">
                    @foreach ($genelGorseller as $gorsel)
                        <button type="button" class="galeri-kucuk-dugme"
                                data-gorsel="{{ asset('storage/' . $gorsel->path) }}">
                            <img src="{{ asset('storage/' . $gorsel->path) }}"
                                 alt="{{ $urun->name }} görsel {{ $loop->iteration }}" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Bilgi ve seçim --}}
        <div class="urun-panel">
            @if ($urun->collection)
                <span class="etiket">{{ $urun->collection->name }}</span>
            @endif

            <h1>{{ $urun->name }}</h1>

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
                            {{ $eksen->name }}
                            @if ($eksen->name === 'Beden' && $bedenTablosu)
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

            <dl class="urun-detay">
                @if ($urun->material)
                    <dt>Kumaş</dt><dd>{{ $urun->material }}</dd>
                @endif
                @if ($urun->model_note)
                    <dt>Model ölçüsü</dt><dd>{{ $urun->model_note }}</dd>
                @endif
                @if ($urun->care_notes)
                    <dt>Bakım</dt><dd>{{ $urun->care_notes }}</dd>
                @endif
                <dt>Kargo ve iade</dt>
                <dd>
                    {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL üzeri kargo ücretsiz.
                    Teslimden sonra {{ config('shop.cayma_hakki_gun') }} gün iade hakkınız var.
                </dd>
            </dl>

            @if ($urun->description)
                <div class="urun-aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @endif
        </div>
    </div>

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
        <section class="bolum belir">
            <div class="bolum-basi">
                <h2>Birlikte kullanın</h2>
            </div>
            <div class="urun-izgara">
                @foreach ($urun->related as $oneri)
                    @include('vitrin.parca.urun-kart', ['urun' => $oneri])
                @endforeach
            </div>
        </section>
    @endif
</div>

@push('betik')
    @vite(['resources/js/urun.js'])
@endpush

<script type="application/json" id="varyant-verisi">@json($varyantVerisi)</script>
<script type="application/json" id="galeri-verisi">@json($galeriVerisi)</script>
@endsection
