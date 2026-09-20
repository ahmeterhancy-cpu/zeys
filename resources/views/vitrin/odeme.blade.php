@extends('layout.vitrin')

@section('baslik', 'Ödeme — ' . config('shop.ad'))

@section('icerik')
<div class="kap odeme-sayfa">

    <div class="bolum-basi">
        <h2>Ödeme</h2>
    </div>

    @if (session('hata'))
        <p class="uyari uyari-hata">{{ session('hata') }}</p>
    @endif

    @error('sozlesme_onay')
        <p class="uyari uyari-hata">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('checkout.store') }}" class="odeme-duzen">
        @csrf

        <div class="odeme-form">
            <fieldset class="form-blok">
                <legend class="etiket">İletişim</legend>

                <div class="alan">
                    <label for="ad">Ad Soyad</label>
                    <input type="text" id="ad" name="ad" value="{{ old('ad') }}"
                           class="metin-girdi" required autocomplete="name">
                    @error('ad') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>

                <div class="alan-ikili">
                    <div class="alan">
                        <label for="eposta">E-posta</label>
                        <input type="email" id="eposta" name="eposta" value="{{ old('eposta') }}"
                               class="metin-girdi" required autocomplete="email">
                        @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="telefon">Telefon</label>
                        <input type="tel" id="telefon" name="telefon" value="{{ old('telefon') }}"
                               class="metin-girdi" required autocomplete="tel">
                        @error('telefon') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-blok">
                <legend class="etiket">Teslimat Adresi</legend>

                <div class="alan">
                    <label for="adres">Adres</label>
                    <input type="text" id="adres" name="adres" value="{{ old('adres') }}"
                           class="metin-girdi" required autocomplete="address-line1">
                    @error('adres') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>

                <div class="alan">
                    <label for="adres2">Apartman, daire (isteğe bağlı)</label>
                    <input type="text" id="adres2" name="adres2" value="{{ old('adres2') }}"
                           class="metin-girdi" autocomplete="address-line2">
                </div>

                <div class="alan-uclu">
                    <div class="alan">
                        <label for="ilce">İlçe</label>
                        <input type="text" id="ilce" name="ilce" value="{{ old('ilce') }}"
                               class="metin-girdi" required autocomplete="address-level2">
                        @error('ilce') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="il">İl</label>
                        <input type="text" id="il" name="il" value="{{ old('il') }}"
                               class="metin-girdi" required autocomplete="address-level1">
                        @error('il') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="posta_kodu">Posta kodu</label>
                        <input type="text" id="posta_kodu" name="posta_kodu" value="{{ old('posta_kodu') }}"
                               class="metin-girdi" autocomplete="postal-code">
                    </div>
                </div>

                <div class="alan">
                    <label for="not">Sipariş notu (isteğe bağlı)</label>
                    <textarea id="not" name="not" rows="3" class="metin-girdi">{{ old('not') }}</textarea>
                </div>
            </fieldset>
        </div>

        <aside class="odeme-ozet">
            <h3>Sipariş Özeti</h3>

            @foreach ($satirlar as $satir)
                <div class="ozet-urun">
                    <span>
                        {{ $satir['product']->name }}
                        <em>{{ $satir['label'] }} × {{ $satir['quantity'] }}</em>
                    </span>
                    <span>{{ number_format($satir['line_total'], 2, ',', '.') }} TL</span>
                </div>
            @endforeach

            <div class="ozet-satir">
                <span>Ara toplam</span>
                <span>{{ number_format($araToplam, 2, ',', '.') }} TL</span>
            </div>

            @if ($indirim > 0)
                <div class="ozet-satir ozet-indirim">
                    <span>İndirim</span>
                    <span>-{{ number_format($indirim, 2, ',', '.') }} TL</span>
                </div>
            @endif

            <div class="ozet-satir">
                <span>Kargo</span>
                <span>{{ $kargo > 0 ? number_format($kargo, 2, ',', '.') . ' TL' : 'Ücretsiz' }}</span>
            </div>

            <div class="ozet-satir ozet-toplam">
                <span>Toplam</span>
                <span>{{ number_format($toplam, 2, ',', '.') }} TL</span>
            </div>

            <label class="onay-kutu">
                <input type="checkbox" name="sozlesme_onay" value="1" required>
                <span>
                    <a href="{{ route('legal', 'on-bilgilendirme') }}" target="_blank" rel="noopener">Ön Bilgilendirme Formu</a>'nu
                    ve
                    <a href="{{ route('legal', 'mesafeli-satis') }}" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>'ni
                    okudum, onaylıyorum.
                </span>
            </label>

            @if ($sozlesme)
                <p class="ozet-not">Onayladığınız sürüm: {{ $sozlesme->version }}</p>
            @endif

            <button type="submit" class="dugme odeme-dugme">Ödemeye geç</button>

            <p class="ozet-not">
                Kart bilgileriniz ödeme kuruluşunun güvenli sayfasında girilir,
                tarafımızca görülmez ve saklanmaz.
            </p>
        </aside>
    </form>
</div>
@endsection
