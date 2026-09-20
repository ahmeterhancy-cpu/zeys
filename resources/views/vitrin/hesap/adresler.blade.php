@extends('layout.vitrin')

@section('baslik', 'Adreslerim — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar hesap-sayfa">

    <div class="hesap-basi">
        <h1>Adreslerim</h1>
    </div>

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif

    <nav class="hesap-menu">
        <a href="{{ route('account.index') }}">Siparişlerim</a>
        <a href="{{ route('account.addresses') }}" class="hesap-menu-aktif">Adreslerim</a>
    </nav>

    @foreach ($adresler as $adres)
        <div class="adres-karti">
            <div>
                @if ($adres->title)
                    <p class="adres-baslik">{{ $adres->title }}</p>
                @endif
                @if ($adres->is_default)
                    <span class="etiket">Varsayılan</span>
                @endif
                <address>
                    {{ $adres->name }}<br>
                    {{ trim($adres->line1 . ' ' . $adres->line2) }}<br>
                    {{ $adres->district }} / {{ $adres->city }}
                    @if ($adres->postal_code) · {{ $adres->postal_code }} @endif
                    <br>{{ $adres->phone }}
                </address>
            </div>

            <form method="POST" action="{{ route('account.address.destroy', $adres) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="metin-dugme metin-dugme-sil">Sil</button>
            </form>
        </div>
    @endforeach

    <section class="bolum">
        <div class="bolum-basi"><h2>Yeni adres</h2></div>

        <form method="POST" action="{{ route('account.address.store') }}">
            @csrf

            <div class="alan-ikili">
                <div class="alan">
                    <label for="baslik">Başlık (isteğe bağlı)</label>
                    <input type="text" id="baslik" name="baslik" value="{{ old('baslik') }}"
                           placeholder="Ev / İş" class="metin-girdi">
                </div>
                <div class="alan">
                    <label for="ad">Ad Soyad</label>
                    <input type="text" id="ad" name="ad" value="{{ old('ad', auth()->user()->name) }}"
                           class="metin-girdi" required>
                    @error('ad') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="alan">
                <label for="telefon">Telefon</label>
                <input type="tel" id="telefon" name="telefon" value="{{ old('telefon') }}"
                       class="metin-girdi" required>
                @error('telefon') <span class="alan-hata">{{ $message }}</span> @enderror
            </div>

            <div class="alan">
                <label for="adres">Adres</label>
                <input type="text" id="adres" name="adres" value="{{ old('adres') }}"
                       class="metin-girdi" required>
                @error('adres') <span class="alan-hata">{{ $message }}</span> @enderror
            </div>

            <div class="alan">
                <label for="adres2">Apartman, daire (isteğe bağlı)</label>
                <input type="text" id="adres2" name="adres2" value="{{ old('adres2') }}" class="metin-girdi">
            </div>

            <div class="alan-uclu">
                <div class="alan">
                    <label for="ilce">İlçe</label>
                    <input type="text" id="ilce" name="ilce" value="{{ old('ilce') }}"
                           class="metin-girdi" required>
                    @error('ilce') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>
                <div class="alan">
                    <label for="il">İl</label>
                    <input type="text" id="il" name="il" value="{{ old('il') }}"
                           class="metin-girdi" required>
                    @error('il') <span class="alan-hata">{{ $message }}</span> @enderror
                </div>
                <div class="alan">
                    <label for="posta_kodu">Posta kodu</label>
                    <input type="text" id="posta_kodu" name="posta_kodu" value="{{ old('posta_kodu') }}"
                           class="metin-girdi">
                </div>
            </div>

            <label class="onay-kutu">
                <input type="checkbox" name="varsayilan" value="1">
                <span>Varsayılan adresim olsun</span>
            </label>

            <button type="submit" class="dugme" style="margin-top:12px">Adresi kaydet</button>
        </form>
    </section>
</div>
@endsection
