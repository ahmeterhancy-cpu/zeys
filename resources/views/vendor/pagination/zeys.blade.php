{{-- Sayfalama — referanstaki kare kutucuklar. Tailwind'siz, sade liste. --}}
@if ($paginator->hasPages())
    <nav class="sayfalar" aria-label="Sayfalar">
        @if ($paginator->onFirstPage())
            <span class="sayfa sayfa-pasif" aria-hidden="true">@include('vitrin.parca.ikon', ['ad' => 'ok-sol'])</span>
        @else
            <a class="sayfa" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Önceki sayfa">@include('vitrin.parca.ikon', ['ad' => 'ok-sol'])</a>
        @endif

        @isset($elements)
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="sayfa sayfa-pasif">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sayfa sayfa-aktif" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="sayfa" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endisset

        @if ($paginator->hasMorePages())
            <a class="sayfa" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Sonraki sayfa">@include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</a>
        @else
            <span class="sayfa sayfa-pasif" aria-hidden="true">@include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
        @endif
    </nav>
@endif
