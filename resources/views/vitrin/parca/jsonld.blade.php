@php
    /*
     * TUZAK: Blade, kaynakta geçen "@context" ifadesini KENDİ yönergesi
     * sanıp yiyor. Bu yüzden yapı burada PHP dizisi olarak kuruluyor ve
     * json_encode ile basılıyor — Blade kaynağında hiçbir yerde düz
     * "@context" metni geçmiyor.
     */
    $jsonLd = null;

    if (($tur ?? null) === 'urun' && isset($urun)) {
        $teklifler = $urun->variants
            ->where('is_active', true)
            ->map(fn ($v) => [
                '@type' => 'Offer',
                'sku' => $v->sku,
                'name' => $v->label,
                'price' => number_format((float) $v->price, 2, '.', ''),
                'priceCurrency' => config('shop.para_birimi'),
                'availability' => $v->available_stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('products.show', $urun->slug),
            ])->values()->all();

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $urun->name,
            'sku' => $urun->base_sku ?: $urun->slug,
            'description' => $urun->short_description ?: $urun->name,
            'brand' => ['@type' => 'Brand', 'name' => config('shop.ad')],
            'offers' => $teklifler,
        ];

        if ($urun->hero_image) {
            $jsonLd['image'] = asset('storage/'.$urun->hero_image);
        }

        if ($urun->material) {
            $jsonLd['material'] = $urun->material;
        }
    }

    if (($tur ?? null) === 'magaza') {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ClothingStore',
            'name' => config('shop.ad'),
            'url' => url('/'),
            'image' => asset('img/zeys-logo.png'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => config('shop.satici.adres'),
                'addressLocality' => 'Edirne',
                'addressCountry' => 'TR',
            ],
            'sameAs' => ['https://instagram.com/'.config('shop.sosyal.instagram')],
        ];

        if (config('shop.satici.telefon')) {
            $jsonLd['telephone'] = config('shop.satici.telefon');
        }
    }
@endphp

@if ($jsonLd)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
