@extends('layout.vitrin')

@section('baslik', 'Giriş Yap — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar yasal-sayfa">
    <h1>Giriş Yap</h1>

    <p style="color: var(--ink-soft); margin-bottom: 28px">
        Hesabınız yoksa <a href="{{ route('register') }}">hesap oluşturabilirsiniz</a>.
        Hesap açmadan da alışveriş yapabilir, siparişinizi
        <a href="{{ route('order.lookup.form') }}">sipariş numarasıyla sorgulayabilirsiniz</a>.
    </p>

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="alan">
            <label for="eposta">E-posta</label>
            <input type="email" id="eposta" name="eposta" value="{{ old('eposta') }}"
                   class="metin-girdi" required autocomplete="email">
            @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <div class="alan">
            <label for="parola">Parola</label>
            <input type="password" id="parola" name="parola"
                   class="metin-girdi" required autocomplete="current-password">
            @error('parola') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <label class="onay-kutu">
            <input type="checkbox" name="beni_hatirla" value="1">
            <span>Beni hatırla</span>
        </label>

        <button type="submit" class="dugme" style="margin-top:14px">Giriş yap</button>
    </form>

    <p style="margin-top:22px"><a href="{{ route('password.request') }}">Parolamı unuttum</a></p>
</div>
@endsection
