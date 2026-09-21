@extends('layout.vitrin')

@section('baslik', 'Hesap Oluştur — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Hesap Oluştur', 'konum' => ['Hesabım' => route('account.index'), 'Hesap Oluştur' => null]])

<div class="kap kap-dar yasal-sayfa kutu-sayfa">

    <p style="color: var(--ink-soft); margin-bottom: 28px">
        Hesap alışveriş için zorunlu değil; siparişlerinizi ve adreslerinizi
        bir arada tutmanızı sağlar.
        Zaten hesabınız varsa <a href="{{ route('login') }}">giriş yapın</a>.
    </p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="alan">
            <label for="ad">Ad Soyad</label>
            <input type="text" id="ad" name="ad" value="{{ old('ad') }}"
                   class="metin-girdi" required autocomplete="name">
            @error('ad') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <div class="alan">
            <label for="eposta">E-posta</label>
            <input type="email" id="eposta" name="eposta" value="{{ old('eposta') }}"
                   class="metin-girdi" required autocomplete="email">
            @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <div class="alan-ikili">
            <div class="alan">
                <label for="parola">Parola</label>
                <input type="password" id="parola" name="parola"
                       class="metin-girdi" required autocomplete="new-password">
                @error('parola') <span class="alan-hata">{{ $message }}</span> @enderror
            </div>

            <div class="alan">
                <label for="parola_confirmation">Parola (tekrar)</label>
                <input type="password" id="parola_confirmation" name="parola_confirmation"
                       class="metin-girdi" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="dugme" style="margin-top:10px">Hesap oluştur</button>
    </form>
</div>
@endsection
