@extends('layout.vitrin')

@section('baslik', 'Sipariş Sorgula — ' . config('shop.ad'))
@section('aciklama', 'Sipariş numaranız ve e-posta adresinizle siparişinizi sorgulayın.')

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Sipariş Sorgula'])

<div class="kap kap-dar yasal-sayfa">

    <p style="color: var(--ink-soft); margin-bottom: 28px">
        Sipariş numaranızı ve sipariş sırasında kullandığınız e-posta adresini girin.
        Sipariş numarası onay e-postanızda yazıyor.
    </p>

    @if (session('hata'))
        <p class="uyari uyari-hata">{{ session('hata') }}</p>
    @endif

    <form method="POST" action="{{ route('order.lookup') }}">
        @csrf

        <div class="alan">
            <label for="siparis_no">Sipariş numarası</label>
            <input type="text" id="siparis_no" name="siparis_no"
                   value="{{ old('siparis_no') }}" placeholder="ZEY-260921-0001"
                   class="metin-girdi" required>
            @error('siparis_no') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <div class="alan">
            <label for="eposta">E-posta</label>
            <input type="email" id="eposta" name="eposta" value="{{ old('eposta') }}"
                   class="metin-girdi" required autocomplete="email">
            @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="dugme" style="margin-top:10px">Siparişi göster</button>
    </form>
</div>
@endsection
