@extends('layout.vitrin')

@section('baslik', 'Parolamı Unuttum — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Parolamı Unuttum', 'konum' => ['Hesabım' => route('account.index'), 'Parolamı Unuttum' => null]])

<div class="kap kap-dar yasal-sayfa kutu-sayfa">

    <p style="color: var(--ink-soft); margin-bottom: 28px">
        Hesabınızın e-posta adresini girin; parola sıfırlama bağlantısı gönderelim.
    </p>

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="alan">
            <label for="eposta">E-posta</label>
            <input type="email" id="eposta" name="eposta" value="{{ old('eposta') }}"
                   class="metin-girdi" required autocomplete="email">
            @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="dugme" style="margin-top:10px">Bağlantı gönder</button>
    </form>

    <p style="margin-top:24px"><a href="{{ route('login') }}">Girişe dön</a></p>
</div>
@endsection
