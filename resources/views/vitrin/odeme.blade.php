@extends('layout.vitrin')

@section('baslik', 'Ödeme — ' . config('shop.ad'))

@section('icerik')
<div class="kap odeme-sayfa">

    <div class="bolum-basi">
        <h2>Ödeme</h2>
    </div>

    @guest
        <p class="uyari">
            Hesabınız varsa <a href="{{ route('login') }}">giriş yapın</a>,
            adres bilgileriniz otomatik dolsun. Hesap açmadan da devam edebilirsiniz.
        </p>
    @endguest

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
                    <input type="text" id="ad" name="ad" value="{{ old('ad', $kayitliAdres->name ?? auth()->user()->name ?? '') }}"
                           class="metin-girdi" required autocomplete="name">
                    @error('ad') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>

                <div class="alan-ikili">
                    <div class="alan">
                        <label for="eposta">E-posta</label>
                        <input type="email" id="eposta" name="eposta" value="{{ old('eposta', auth()->user()->email ?? '') }}"
                               class="metin-girdi" required autocomplete="email">
                        @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="telefon">Telefon</label>
                        <input type="tel" id="telefon" name="telefon" value="{{ old('telefon', $kayitliAdres->phone ?? '') }}"
                               class="metin-girdi" required autocomplete="tel">
                        @error('telefon') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-blok">
                <legend class="etiket">Teslimat Adresi</legend>

                <div class="alan">
                    <label for="adres">Adres</label>
                    <input type="text" id="adres" name="adres" value="{{ old('adres', $kayitliAdres->line1 ?? '') }}"
                           class="metin-girdi" required autocomplete="address-line1">
                    @error('adres') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>

                <div class="alan">
                    <label for="adres2">Apartman, daire (isteğe bağlı)</label>
                    <input type="text" id="adres2" name="adres2" value="{{ old('adres2', $kayitliAdres->line2 ?? '') }}"
                           class="metin-girdi" autocomplete="address-line2">
                </div>

                <div class="alan-uclu">
                    <div class="alan">
                        <label for="ilce">İlçe</label>
                        <input type="text" id="ilce" name="ilce" value="{{ old('ilce', $kayitliAdres->district ?? '') }}"
                               class="metin-girdi" required autocomplete="address-level2">
                        @error('ilce') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="il">İl</label>
                        <input type="text" id="il" name="il" value="{{ old('il', $kayitliAdres->city ?? '') }}"
                               class="metin-girdi" required autocomplete="address-level1">
                        @error('il') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan">
                        <label for="posta_kodu">Posta kodu</label>
                        <input type="text" id="posta_kodu" name="posta_kodu" value="{{ old('posta_kodu', $kayitliAdres->postal_code ?? '') }}"
                               class="metin-girdi" autocomplete="postal-code">
                    </div>
                </div>

                <div class="alan">
                    <label for="not">Sipariş notu (isteğe bağlı)</label>
                    <textarea id="not" name="not" rows="3" class="metin-girdi">{{ old('not') }}</textarea>
                </div>
            </fieldset>

            @php
                $faturaTipi = old('fatura_tipi', ($kayitliAdres->invoice_type ?? null) === 'corporate' ? 'kurumsal' : 'bireysel');
                $kayitliKurumsal = ($kayitliAdres->invoice_type ?? null) === 'corporate';
            @endphp

            {{--
                Fatura (e-Arşiv). Kurumsal alanlar CSS :has() ile gösterilip
                gizleniyor; JS gerekmez. :has desteklemeyen tarayıcıda alanlar
                yalnızca hep görünür kalır — form yine çalışır.
            --}}
            <fieldset class="form-blok fatura-blok">
                <legend class="etiket">Fatura</legend>

                <div class="fatura-tipi">
                    <label class="onay-kutu">
                        <input type="radio" name="fatura_tipi" value="bireysel" @checked($faturaTipi === 'bireysel')>
                        <span>Bireysel</span>
                    </label>
                    <label class="onay-kutu">
                        <input type="radio" name="fatura_tipi" value="kurumsal" @checked($faturaTipi === 'kurumsal')>
                        <span>Kurumsal (şirket adına)</span>
                    </label>
                </div>

                <div class="bireysel-alanlar">
                    <div class="alan">
                        <label for="tckn">T.C. kimlik no (isteğe bağlı)</label>
                        <input type="text" id="tckn" name="tckn" inputmode="numeric" maxlength="11"
                               value="{{ old('tckn', ! $kayitliKurumsal ? ($kayitliAdres->tax_number ?? '') : '') }}"
                               class="metin-girdi" autocomplete="off">
                        <span class="alan-ipucu">e-Arşiv faturanızda yer alır. Boş bırakabilirsiniz.</span>
                        @error('tckn') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="kurumsal-alanlar">
                    <div class="alan">
                        <label for="firma_unvani">Firma unvanı</label>
                        <input type="text" id="firma_unvani" name="firma_unvani"
                               value="{{ old('firma_unvani', $kayitliAdres->company_name ?? '') }}"
                               class="metin-girdi" autocomplete="organization">
                        @error('firma_unvani') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>

                    <div class="alan-ikili">
                        <div class="alan">
                            <label for="vergi_dairesi">Vergi dairesi</label>
                            <input type="text" id="vergi_dairesi" name="vergi_dairesi"
                                   value="{{ old('vergi_dairesi', $kayitliAdres->tax_office ?? '') }}"
                                   class="metin-girdi">
                            @error('vergi_dairesi') <span class="alan-hata">{{ $message }}</span> @enderror
                        </div>
                        <div class="alan">
                            <label for="vkn">Vergi no (VKN)</label>
                            <input type="text" id="vkn" name="vkn" inputmode="numeric" maxlength="10"
                                   value="{{ old('vkn', $kayitliKurumsal ? ($kayitliAdres->tax_number ?? '') : '') }}"
                                   class="metin-girdi">
                            @error('vkn') <span class="alan-hata">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <label class="onay-kutu">
                    <input type="checkbox" name="farkli_fatura_adresi" value="1" @checked(old('farkli_fatura_adresi'))>
                    <span>Fatura adresim teslimat adresinden farklı</span>
                </label>

                <div class="fatura-adres-alanlari">
                    <div class="alan">
                        <label for="fatura_adres">Fatura adresi</label>
                        <input type="text" id="fatura_adres" name="fatura_adres" value="{{ old('fatura_adres') }}" class="metin-girdi">
                        @error('fatura_adres') <span class="alan-hata">{{ $message }}</span> @enderror
                    </div>
                    <div class="alan-ikili">
                        <div class="alan">
                            <label for="fatura_ilce">İlçe</label>
                            <input type="text" id="fatura_ilce" name="fatura_ilce" value="{{ old('fatura_ilce') }}" class="metin-girdi">
                            @error('fatura_ilce') <span class="alan-hata">{{ $message }}</span> @enderror
                        </div>
                        <div class="alan">
                            <label for="fatura_il">İl</label>
                            <input type="text" id="fatura_il" name="fatura_il" value="{{ old('fatura_il') }}" class="metin-girdi">
                            @error('fatura_il') <span class="alan-hata">{{ $message }}</span> @enderror
                        </div>
                    </div>
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
