@extends('layout.vitrin')

@section('baslik', 'Verilerim — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Verilerim', 'konum' => ['Hesabım' => route('account.index'), 'Verilerim' => null]])

<div class="kap hesap-duzen">
    @include('vitrin.parca.hesap-menu', ['aktif' => 'verilerim'])

<div class="hesap-icerik">


    <section class="bolum bolum-ilk">
        <div class="bolum-basi"><h2>Verilerimi indir</h2></div>

        <p style="color: var(--ink-soft); max-width: var(--measure)">
            KVKK kapsamında hakkınızda tuttuğumuz kişisel verilerin bir kopyasını
            indirebilirsiniz: hesap bilgileri, adresler, siparişler, iade talepleri,
            ürün değerlendirmeleri ve stok bildirim kayıtları.
        </p>

        <a class="dugme dugme-cizgi" href="{{ route('account.data.export') }}">Verilerimi indir (JSON)</a>
    </section>

    <section class="bolum">
        <div class="bolum-basi"><h2>Hesabımı sil</h2></div>

        <div class="uyari">
            <p style="margin:0 0 8px"><strong>Silinecekler:</strong> hesabınız, kayıtlı adresleriniz, ürün değerlendirmeleriniz ve stok bildirim kayıtlarınız.</p>
            <p style="margin:0">
                <strong>Silinmeyecekler:</strong> sipariş ve fatura kayıtları. Vergi mevzuatı
                gereği yasal süre boyunca saklanmak zorundadır; hesabınızdan ayrılır ama
                silinmez. Bu siparişleri sipariş numaranız ve e-postanızla sorgulamaya
                devam edebilirsiniz.
            </p>
        </div>

        <form method="POST" action="{{ route('account.destroy') }}">
            @csrf
            @method('DELETE')

            <div class="alan">
                <label for="parola">Onaylamak için parolanız</label>
                <input type="password" id="parola" name="parola" class="metin-girdi"
                       required autocomplete="current-password">
                @error('parola') <span class="alan-hata">{{ $message }}</span> @enderror
            </div>

            <label class="onay-kutu">
                <input type="checkbox" name="onay" value="1" required>
                <span>Hesabımın kalıcı olarak silineceğini anlıyorum.</span>
            </label>
            @error('onay') <span class="alan-hata">{{ $message }}</span> @enderror

            <button type="submit" class="dugme dugme-sil" style="margin-top:14px">Hesabımı sil</button>
        </form>
    </section>
</div>
</div>
@endsection
