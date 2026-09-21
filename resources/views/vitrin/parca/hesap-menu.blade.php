{{-- Hesap yan menüsü — referansın "My Account" gezinmesi. $aktif: siparisler|adresler|verilerim --}}
<nav class="hesap-menu" aria-label="Hesap menüsü">
    <div class="hesap-menu-kim">
        <strong>{{ auth()->user()->name }}</strong>
        <span>{{ auth()->user()->email }}</span>
    </div>
    <a href="{{ route('account.index') }}" @class(['hesap-menu-aktif' => $aktif === 'siparisler'])>
        @include('vitrin.parca.ikon', ['ad' => 'sepet']) Siparişlerim
    </a>
    <a href="{{ route('account.addresses') }}" @class(['hesap-menu-aktif' => $aktif === 'adresler'])>
        @include('vitrin.parca.ikon', ['ad' => 'konum']) Adreslerim
    </a>
    <a href="{{ route('account.data') }}" @class(['hesap-menu-aktif' => $aktif === 'verilerim'])>
        @include('vitrin.parca.ikon', ['ad' => 'kalkan']) Verilerim
    </a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">@include('vitrin.parca.ikon', ['ad' => 'ok-sol']) Çıkış yap</button>
    </form>
</nav>
