{{--
    Mağaza düzeni — referansın "Left Sidebar" dükkân sayfası:
    solda kategoriler / koleksiyonlar / fiyat, sağda araç çubuğu + ızgara.

    Değişkenler: $urunler (sayfalayıcı), $bosMesaj, isteğe bağlı $aciklama,
    $aktifKategori (Category), $aktifKoleksiyon (Collection), $altKategoriler.
    $menuKategorileri ve $menuKoleksiyonlari görünüm bağlayıcısından gelir.
--}}
@php
    $sira = \App\Support\Katalog::sira(request());
    $aktifKatId = isset($aktifKategori) ? $aktifKategori->id : null;
    $aktifKolId = isset($aktifKoleksiyon) ? $aktifKoleksiyon->id : null;
    // Sıralama formu diğer süzgeçleri korusun
    $korunan = collect(request()->query())->except(['sirala', 'page'])->filter(fn ($v) => is_scalar($v) && $v !== '');
@endphp

<div class="kap magaza-duzen">
    <aside class="magaza-yan" aria-label="Süzgeçler">
        <details class="magaza-yan-kap" open>
            <summary class="magaza-yan-ac">
                @include('vitrin.parca.ikon', ['ad' => 'filtre']) Süzgeçler
            </summary>

            @if ($menuKategorileri->isNotEmpty())
                <section class="yan-blok">
                    <h2>Kategoriler</h2>
                    <ul class="yan-liste">
                        @foreach ($menuKategorileri as $kat)
                            <li>
                                <a href="{{ route('catalog.category', $kat->slug) }}"
                                   @class(['aktif' => $aktifKatId === $kat->id || $kat->children->contains('id', $aktifKatId)])>
                                    <span>{{ $kat->name }}</span>
                                    <span class="yan-sayi">{{ $kat->products_count }}</span>
                                </a>
                                @if ($kat->children->isNotEmpty() && ($aktifKatId === $kat->id || $kat->children->contains('id', $aktifKatId)))
                                    <ul class="yan-liste yan-liste-alt">
                                        @foreach ($kat->children as $alt)
                                            <li>
                                                <a href="{{ route('catalog.category', $alt->slug) }}" @class(['aktif' => $aktifKatId === $alt->id])>
                                                    <span>{{ $alt->name }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($menuKoleksiyonlari->isNotEmpty())
                <section class="yan-blok">
                    <h2>Koleksiyonlar</h2>
                    <ul class="yan-liste">
                        @foreach ($menuKoleksiyonlari as $kol)
                            <li>
                                <a href="{{ route('collections.show', $kol->slug) }}" @class(['aktif' => $aktifKolId === $kol->id])>
                                    <span>{{ $kol->name }}</span>
                                    <span class="yan-sayi">{{ $kol->products_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="yan-blok">
                <h2>Fiyat</h2>
                <form method="GET" class="fiyat-suzgec">
                    @foreach ($korunan->except(['fiyat_min', 'fiyat_max']) as $ad => $deger)
                        <input type="hidden" name="{{ $ad }}" value="{{ $deger }}">
                    @endforeach
                    @if ($sira !== 'onerilen')
                        <input type="hidden" name="sirala" value="{{ $sira }}">
                    @endif
                    <div class="fiyat-alanlar">
                        <label>
                            <span class="gorunmez">En düşük fiyat (TL)</span>
                            <input type="number" name="fiyat_min" min="0" step="1" placeholder="En az"
                                   value="{{ request('fiyat_min') }}" class="metin-girdi">
                        </label>
                        <span aria-hidden="true">–</span>
                        <label>
                            <span class="gorunmez">En yüksek fiyat (TL)</span>
                            <input type="number" name="fiyat_max" min="0" step="1" placeholder="En çok"
                                   value="{{ request('fiyat_max') }}" class="metin-girdi">
                        </label>
                    </div>
                    <button type="submit" class="dugme dugme-koyu">Süz</button>
                    @if (request()->filled('fiyat_min') || request()->filled('fiyat_max'))
                        <a class="metin-dugme" href="{{ request()->url() }}?{{ http_build_query($korunan->except(['fiyat_min', 'fiyat_max'])->all()) }}">Temizle</a>
                    @endif
                </form>
            </section>
        </details>
    </aside>

    <div class="magaza-ana">
        @if (! empty($aciklama))
            <p class="magaza-aciklama">{{ $aciklama }}</p>
        @endif

        @if (isset($altKategoriler) && $altKategoriler->isNotEmpty())
            <div class="alt-kategoriler">
                @foreach ($altKategoriler as $alt)
                    <a href="{{ route('catalog.category', $alt->slug) }}">{{ $alt->name }}</a>
                @endforeach
            </div>
        @endif

        @yield('katalog_ust')

        @if ($urunler === null)
            {{-- Arama sayfası, henüz sorgu yok --}}
        @elseif ($urunler->isEmpty())
            <div class="bos-durum">
                <p>{{ $bosMesaj }}</p>
                <a class="dugme" href="{{ route('collections.index') }}">Tüm ürünlere bak</a>
            </div>
        @else
            <div class="arac-cubugu">
                <p class="arac-sayi">
                    {{ $urunler->total() }} ürünün
                    {{ $urunler->firstItem() }}–{{ $urunler->lastItem() }} arası gösteriliyor
                </p>

                <form method="GET" class="sirala-formu">
                    @foreach ($korunan as $ad => $deger)
                        <input type="hidden" name="{{ $ad }}" value="{{ $deger }}">
                    @endforeach
                    <label for="sirala">Sırala</label>
                    <select id="sirala" name="sirala" data-otomatik-gonder>
                        @foreach (\App\Support\Katalog::SIRALAR as $anahtar => $etiket)
                            <option value="{{ $anahtar }}" @selected($sira === $anahtar)>{{ $etiket }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="dugme-kucuk">Uygula</button></noscript>
                </form>
            </div>

            <div class="urun-izgara urun-izgara-3">
                @foreach ($urunler as $urun)
                    @include('vitrin.parca.urun-kart', ['urun' => $urun])
                @endforeach
            </div>

            <div class="sayfalama">{{ $urunler->links() }}</div>
        @endif
    </div>
</div>
