@extends('layout.vitrin')

@section('baslik', 'Yeni Parola — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Yeni Parola', 'konum' => ['Hesabım' => route('account.index'), 'Yeni Parola' => null]])

<div class="kap kap-dar yasal-sayfa kutu-sayfa">

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="alan">
            <label for="eposta">E-posta</label>
            <input type="email" id="eposta" name="eposta" value="{{ old('eposta', $eposta) }}"
                   class="metin-girdi" required autocomplete="email">
            @error('eposta') <span class="alan-hata">{{ $message }}</span> @enderror
        </div>

        <div class="alan-ikili">
            <div class="alan">
                <label for="parola">Yeni parola</label>
                <input type="password" id="parola" name="parola"
                       class="metin-girdi" required autocomplete="new-password">
                @error('parola') <span class="alan-hata">{{ $message }}</span> @enderror
            </div>
            <div class="alan">
                <label for="parola_confirmation">Yeni parola (tekrar)</label>
                <input type="password" id="parola_confirmation" name="parola_confirmation"
                       class="metin-girdi" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="dugme" style="margin-top:10px">Parolayı değiştir</button>
    </form>
</div>
@endsection
