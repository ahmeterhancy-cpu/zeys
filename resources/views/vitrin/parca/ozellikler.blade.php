{{-- Özellik şeridi — referanstaki dört kutu; yalnız doğru olan vaatler --}}
@php $esik = (float) config('shop.kargo.ucretsiz_esigi'); @endphp
<section class="kap bolum">
    <ul class="ozellikler">
        <li>
            @include('vitrin.parca.ikon', ['ad' => 'kamyon'])
            <div>
                <h3>{{ $esik > 0 ? 'Ücretsiz Kargo' : 'Türkiye Geneli Kargo' }}</h3>
                <p>{{ $esik > 0 ? number_format($esik, 0, ',', '.') . ' TL ve üzeri siparişlerde' : 'Edirne\'den tüm Türkiye\'ye' }}</p>
            </div>
        </li>
        <li>
            @include('vitrin.parca.ikon', ['ad' => 'kalkan'])
            <div>
                <h3>Güvenli Ödeme</h3>
                <p>Kart bilgileriniz PayTR altyapısında</p>
            </div>
        </li>
        <li>
            @include('vitrin.parca.ikon', ['ad' => 'iade'])
            <div>
                <h3>{{ config('shop.cayma_hakki_gun') }} Gün İade</h3>
                <p>Teslimden sonra gerekçesiz cayma hakkı</p>
            </div>
        </li>
        <li>
            @include('vitrin.parca.ikon', ['ad' => 'destek'])
            <div>
                <h3>Mağaza Desteği</h3>
                <p>Sorularınız için Instagram ve telefon</p>
            </div>
        </li>
    </ul>
</section>
